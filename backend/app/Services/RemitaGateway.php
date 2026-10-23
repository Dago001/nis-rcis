<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Payment;
use App\Services\Contracts\PaymentGatewayInterface;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Remita (Remita.net by SystemSpecs) Payment Gateway Integration.
 *
 * Official payment platform for the Federal Government of Nigeria
 * Treasury Single Account (TSA) and Nigeria Immigration Service (NIS).
 *
 * All payments are verified server-to-server with SHA-512 cryptographic
 * hashing before any transaction is trusted or approved.
 */
class RemitaGateway implements PaymentGatewayInterface
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Whether simulated payments are active.
     * Development/testing only when REMITA_MERCHANT_ID or REMITA_API_KEY is not configured
     * or when PAYMENTS_FAKE=true. Never permitted in production.
     */
    public function isFake(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        return (bool) config('nis.remita.fake') || blank(config('nis.remita.merchant_id')) || blank(config('nis.remita.api_key'));
    }

    /**
     * Formatted receipt metadata for applicant slips and PDF generation.
     *
     * @return array{date: ?string, gateway: string, mode: ?string, gateway_reference: ?string, pan: ?string}
     */
    public static function receipt(Payment $payment): array
    {
        $data = $payment->gateway_response ?? [];
        $rrr = $data['RRR'] ?? $data['rrr'] ?? null;
        $channel = $data['payment_channel'] ?? $data['channel'] ?? $payment->channel;
        $pan = $data['pan'] ?? $data['card_number'] ?? null;

        return [
            'date' => $payment->paid_at?->toIso8601String(),
            'gateway' => $payment->channel === 'fake' ? 'Remita TSA (Test simulation)' : 'Remita (TSA)',
            'mode' => match ($channel) {
                null => 'Online (Remita TSA)',
                'fake' => 'Test simulation',
                'CARD' => 'Debit/Credit Card',
                'BANK_BRANCH' => 'Bank Branch (Over-the-Counter)',
                'BANK_TRANSFER' => 'Bank Transfer',
                'USSD' => 'USSD Banking',
                'INTERNET_BANKING' => 'Internet Banking',
                'WALLET' => 'Remita Wallet',
                default => ucwords(str_replace('_', ' ', (string) $channel)),
            },
            'gateway_reference' => $rrr ? "RRR: {$rrr}" : ($data['orderId'] ?? (string) $payment->reference),
            'pan' => $pan,
        ];
    }

    /**
     * Initialize transaction and generate Remita Retrieval Reference (RRR).
     *
     * @return array{
     *     payment: Payment,
     *     authorization_url: ?string,
     *     access_code: ?string,
     *     public_key: ?string,
     *     rrr: ?string,
     *     gateway: string,
     *     fake: bool
     * }
     */
    public function initialize(Applicant $applicant, int $amountKobo, string $callbackUrl): array
    {
        $reference = $this->numbers->paymentReference();
        $amountNaira = number_format($amountKobo / 100, 2, '.', '');

        $payment = Payment::create([
            'applicant_id' => $applicant->id,
            'provider' => 'REMITA',
            'reference' => $reference,
            'amount_kobo' => $amountKobo,
            'currency' => 'NGN',
            'status' => 'INITIALIZED',
        ]);

        if ($this->isFake()) {
            $fakeRrr = '2' . str_pad((string) random_int(10000000000, 99999999999), 11, '0', STR_PAD_LEFT);
            $authUrl = config('nis.frontend_url') . '/portal/apply?payment=callback&reference=' . urlencode($reference) . '&rrr=' . $fakeRrr;

            $gatewayResponse = [
                'statuscode' => '025',
                'RRR' => $fakeRrr,
                'status' => 'Payment Reference Generated (Simulation)',
                'orderId' => $reference,
                'amount' => (float) $amountNaira,
                'is_simulated' => true,
            ];

            $payment->gateway_response = $gatewayResponse;
            $payment->save();

            return [
                'payment' => $payment,
                'authorization_url' => $authUrl,
                'access_code' => $fakeRrr,
                'public_key' => config('nis.remita.public_key'),
                'rrr' => $fakeRrr,
                'gateway' => 'remita',
                'fake' => true,
            ];
        }

        $merchantId = (string) config('nis.remita.merchant_id');
        $serviceTypeId = (string) config('nis.remita.service_type_id');
        $apiKey = (string) config('nis.remita.api_key');
        $baseUrl = rtrim((string) config('nis.remita.base_url', 'https://login.remita.net'), '/');

        // Remita SHA-512 Hash format: merchantId + serviceTypeId + orderId + amount + apiKey
        $hash = hash('sha512', $merchantId . $serviceTypeId . $reference . $amountNaira . $apiKey);

        $payerName = trim("{$applicant->surname} {$applicant->forenames}");
        if (blank($payerName)) {
            $payerName = 'ECOWAS Residence Card Applicant';
        }

        $payload = [
            'serviceTypeId' => $serviceTypeId,
            'amount' => $amountNaira,
            'orderId' => $reference,
            'payerName' => $payerName,
            'payerEmail' => $applicant->email,
            'payerPhone' => $applicant->phone ?: '08000000000',
            'description' => 'NIS ECOWAS Residence Card Application Fee',
        ];

        $endpoint = "{$baseUrl}/echannelsvc/merchant/api/paymentstatus/{$merchantId}/{$hash}/post.reg";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "remitaConsumerKey={$merchantId},remitaConsumerToken={$hash}",
        ])->timeout(25)->post($endpoint, $payload);

        $responseData = $this->parseRemitaResponse($response);

        $rrr = $responseData['RRR'] ?? $responseData['rrr'] ?? null;
        if (! $rrr) {
            $errorMsg = $responseData['status'] ?? $responseData['message'] ?? 'Unable to generate Remita Retrieval Reference (RRR).';
            Log::error('Remita RRR Generation Failed', [
                'reference' => $reference,
                'status' => $response->status(),
                'response' => $responseData,
            ]);
            throw new RuntimeException("Remita error: {$errorMsg}");
        }

        // Standard Remita OnePage checkout URL
        $authorizationUrl = "{$baseUrl}/remita/onepage/b/line_items_pmt.spa?rrr={$rrr}";

        $payment->gateway_response = array_merge($payment->gateway_response ?? [], [
            'RRR' => $rrr,
            'orderId' => $reference,
            'serviceTypeId' => $serviceTypeId,
            'initial_response' => $responseData,
        ]);
        $payment->save();

        return [
            'payment' => $payment,
            'authorization_url' => $authorizationUrl,
            'access_code' => $rrr,
            'public_key' => config('nis.remita.public_key'),
            'rrr' => $rrr,
            'gateway' => 'remita',
            'fake' => false,
        ];
    }

    /**
     * Query Remita to verify payment status and update the Payment record.
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->isSuccessful()) {
            return $payment;
        }

        $data = $payment->gateway_response ?? [];
        $rrr = $data['RRR'] ?? $data['rrr'] ?? null;

        if ($this->isFake() || ($data['is_simulated'] ?? false)) {
            $simulatedData = [
                'status' => '00',
                'statuscode' => '00',
                'message' => 'Approved',
                'amount' => $payment->amount_kobo / 100,
                'RRR' => $rrr ?: '2' . str_pad((string) random_int(10000000000, 99999999999), 11, '0', STR_PAD_LEFT),
                'orderId' => $payment->reference,
                'paymentDate' => now()->toIso8601String(),
                'channel' => 'fake',
            ];

            return $this->record($payment, $simulatedData);
        }

        if (! $rrr) {
            throw new RuntimeException("Cannot verify Remita payment: RRR is missing on reference {$payment->reference}.");
        }

        $merchantId = (string) config('nis.remita.merchant_id');
        $apiKey = (string) config('nis.remita.api_key');
        $baseUrl = rtrim((string) config('nis.remita.base_url', 'https://login.remita.net'), '/');

        // Remita Verification Hash: hash('sha512', rrr + apiKey + merchantId)
        $hash = hash('sha512', $rrr . $apiKey . $merchantId);

        $endpoint = "{$baseUrl}/echannelsvc/{$merchantId}/{$rrr}/{$hash}/status.reg";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "remitaConsumerKey={$merchantId},remitaConsumerToken={$hash}",
        ])->timeout(20)->get($endpoint);

        $responseData = $this->parseRemitaResponse($response);

        return $this->record($payment, $responseData);
    }

    /**
     * Refund a payment through Remita.
     *
     * @return array{ok: bool, reference: ?string, message: string}
     */
    public function refund(Payment $payment): array
    {
        if ($this->isFake() || $payment->channel === 'fake') {
            return [
                'ok' => true,
                'reference' => 'REMITA-REFUND-SIM-' . $payment->reference,
                'message' => 'Test mode: Remita TSA refund simulated successfully.',
            ];
        }

        $rrr = $payment->gateway_response['RRR'] ?? $payment->gateway_response['rrr'] ?? null;
        if (! $rrr) {
            return ['ok' => false, 'reference' => null, 'message' => 'Missing Remita RRR for refund processing.'];
        }

        // Remita electronic reversal/refund request
        $merchantId = (string) config('nis.remita.merchant_id');
        $apiKey = (string) config('nis.remita.api_key');
        $baseUrl = rtrim((string) config('nis.remita.base_url', 'https://login.remita.net'), '/');
        $amount = number_format($payment->amount_kobo / 100, 2, '.', '');

        $hash = hash('sha512', $merchantId . $rrr . $amount . $apiKey);

        $endpoint = "{$baseUrl}/echannelsvc/merchant/api/refund";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "remitaConsumerKey={$merchantId},remitaConsumerToken={$hash}",
        ])->timeout(25)->post($endpoint, [
            'merchantId' => $merchantId,
            'rrr' => $rrr,
            'amount' => $amount,
            'requestId' => 'REF-' . $payment->reference,
        ]);

        $data = $this->parseRemitaResponse($response);
        $status = $data['statuscode'] ?? $data['status'] ?? null;
        $isOk = $response->successful() && in_array((string) $status, ['00', '01', '025'], true);

        return [
            'ok' => $isOk,
            'reference' => $data['refundReference'] ?? $data['RRR'] ?? null,
            'message' => (string) ($data['message'] ?? $data['status'] ?? 'Refund processed via Remita.'),
        ];
    }

    /**
     * Process an incoming Remita Instant Payment Notification (IPN / Webhook).
     */
    public function handleWebhook(string $rawBody, ?string $signature): bool
    {
        if (blank($rawBody)) {
            return false;
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return false;
        }

        // Handle array of notifications or single notification object
        $records = isset($payload[0]) && is_array($payload[0]) ? $payload : [$payload];

        foreach ($records as $item) {
            $reference = $item['orderRef'] ?? $item['orderId'] ?? null;
            $rrr = $item['rrr'] ?? $item['RRR'] ?? null;

            $payment = null;
            if ($reference) {
                $payment = Payment::where('reference', $reference)->first();
            }

            if (! $payment && $rrr) {
                $payment = Payment::where('gateway_response->RRR', $rrr)->first();
            }

            if ($payment) {
                // Zero-trust verification: always query Remita Status API directly
                try {
                    $this->verify($payment);
                } catch (\Throwable $e) {
                    Log::error('Remita webhook re-verification failed', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * Parse and sanitize Remita JSON/JSONP response strings.
     */
    private function parseRemitaResponse(Response $response): array
    {
        $body = trim($response->body());

        // Strip jsonp wrapper: jsonp ( { ... } ) or jsonp({ ... });
        if (preg_match('/^jsonp\s*\((.*)\)\s*;?$/is', $body, $matches)) {
            $body = trim($matches[1]);
        }

        $data = json_decode($body, true);
        if (is_array($data)) {
            return $data;
        }

        return ['raw_body' => $body, 'status_code' => $response->status()];
    }

    /**
     * Update payment state inside database transaction with row lock.
     */
    private function record(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isSuccessful()) {
                return $payment;
            }

            $statusCode = (string) ($data['status'] ?? $data['statuscode'] ?? $data['responseCode'] ?? '');
            $isApproved = in_array($statusCode, ['00', '01'], true);

            // Verify amount if provided in response
            $amountMatches = true;
            if (isset($data['amount'])) {
                $returnedKobo = (int) round(((float) $data['amount']) * 100);
                $amountMatches = ($returnedKobo === (int) $payment->amount_kobo);
            }

            $ok = $isApproved && $amountMatches;

            $existingResponse = $payment->gateway_response ?? [];
            $mergedResponse = array_merge($existingResponse, $data);

            $paidAt = null;
            if ($ok) {
                $rawDate = $data['paymentDate'] ?? $data['transactiontime'] ?? null;
                if ($rawDate) {
                    try {
                        $paidAt = Carbon::parse($rawDate);
                    } catch (\Throwable) {
                        $paidAt = now();
                    }
                } else {
                    $paidAt = now();
                }
            }

            $channel = $data['channel'] ?? $data['payment_channel'] ?? ($ok ? 'remita' : null);

            $payment->fill([
                'status' => $ok ? 'SUCCESS' : ($statusCode === '021' || $statusCode === '025' ? 'INITIALIZED' : 'FAILED'),
                'channel' => $channel,
                'paid_at' => $paidAt,
                'verified_at' => $ok ? now() : null,
                'gateway_response' => $mergedResponse,
            ])->save();

            Audit::log(
                $ok ? 'PAYMENT_VERIFIED' : 'PAYMENT_FAILED',
                "Payment {$payment->reference} via Remita TSA",
                $payment,
                [
                    'amount_kobo' => $payment->amount_kobo,
                    'remita_status' => $statusCode,
                    'rrr' => $mergedResponse['RRR'] ?? null,
                ]
            );

            return $payment;
        });
    }
}

<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Payment;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack integration. A payment is only ever marked SUCCESS after the
 * backend itself confirms it with Paystack (verify API or signed webhook),
 * and only if the amount and currency match what we asked for.
 */
class PaystackGateway
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Simulated payments: development only, and only while no Paystack
     * secret key is configured. As soon as PAYSTACK_SECRET_KEY is set, real
     * Paystack checkout is used (test keys sk_test_... in development).
     */
    public function isFake(): bool
    {
        return config('nis.paystack.fake') && ! app()->isProduction() && blank(config('nis.paystack.secret_key'));
    }

    /**
     * Receipt details for slips: never the reusable authorization code.
     *
     * @return array{date: ?string, gateway: string, mode: ?string, gateway_reference: ?string, pan: ?string}
     */
    public static function receipt(Payment $payment): array
    {
        $data = $payment->gateway_response ?? [];
        $auth = $data['authorization'] ?? [];
        $pan = isset($auth['last4']) ? ($auth['bin'] ?? '').'XXXXXX'.$auth['last4'] : null;

        return [
            'date' => $payment->paid_at?->toIso8601String(),
            'gateway' => $payment->channel === 'fake' ? 'Paystack (test simulation)' : 'Paystack',
            'mode' => match ($payment->channel) {
                null => null,
                'fake' => 'Test simulation',
                default => ucwords(str_replace('_', ' ', $payment->channel)),
            },
            'gateway_reference' => isset($data['id']) ? (string) $data['id'] : null,
            'pan' => $pan,
        ];
    }

    /**
     * @return array{payment: Payment, authorization_url: ?string, access_code: ?string, public_key: ?string, fake: bool}
     */
    public function initialize(Applicant $applicant, int $amountKobo, string $callbackUrl): array
    {
        $payment = Payment::create([
            'applicant_id' => $applicant->id,
            'reference' => $this->numbers->paymentReference(),
            'amount_kobo' => $amountKobo,
            'currency' => 'NGN',
            'status' => 'INITIALIZED',
        ]);

        if ($this->isFake()) {
            return ['payment' => $payment, 'authorization_url' => null, 'access_code' => null, 'public_key' => null, 'fake' => true];
        }

        $response = $this->client()->post('/transaction/initialize', [
            'email' => $applicant->email,
            'amount' => $amountKobo,
            'currency' => 'NGN',
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'metadata' => ['applicant_id' => $applicant->id, 'purpose' => 'RESIDENCE_CARD_FEE'],
        ])->throw()->json('data');

        return [
            'payment' => $payment,
            'authorization_url' => $response['authorization_url'] ?? null,
            'access_code' => $response['access_code'] ?? null,
            'public_key' => config('nis.paystack.public_key'),
            'fake' => false,
        ];
    }

    /**
     * Confirm the payment with Paystack and record the outcome.
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->isSuccessful()) {
            return $payment;
        }

        $data = $this->isFake()
            ? ['status' => 'success', 'amount' => $payment->amount_kobo, 'currency' => 'NGN', 'channel' => 'fake', 'paid_at' => now()->toIso8601String()]
            : $this->client()->get('/transaction/verify/'.rawurlencode($payment->reference))->throw()->json('data');

        return $this->record($payment, $data);
    }

    /**
     * Handle a webhook. Returns false if the signature is invalid.
     */
    public function handleWebhook(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('nis.paystack.secret_key');
        if ($secret === '' || ! $signature || ! hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature)) {
            return false;
        }

        $event = json_decode($rawBody, true);
        if (($event['event'] ?? null) === 'charge.success') {
            $payment = Payment::where('reference', $event['data']['reference'] ?? '')->first();
            // Never trust the webhook body alone: re-verify with the API.
            if ($payment) {
                $this->verify($payment);
            }
        }

        return true;
    }

    private function record(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isSuccessful()) {
                return $payment;
            }

            $ok = ($data['status'] ?? null) === 'success'
                && (int) ($data['amount'] ?? 0) === (int) $payment->amount_kobo
                && ($data['currency'] ?? null) === $payment->currency;

            $payment->fill([
                'status' => $ok ? 'SUCCESS' : 'FAILED',
                'channel' => $data['channel'] ?? null,
                'paid_at' => $ok ? ($data['paid_at'] ?? now()) : null,
                'verified_at' => $ok ? now() : null,
                'gateway_response' => $data,
            ])->save();

            Audit::log($ok ? 'PAYMENT_VERIFIED' : 'PAYMENT_FAILED', "Payment {$payment->reference}", $payment,
                ['amount_kobo' => $payment->amount_kobo, 'gateway_status' => $data['status'] ?? null]);

            return $payment;
        });
    }

    private function client()
    {
        $secret = config('nis.paystack.secret_key');
        if (! $secret) {
            throw new RuntimeException('PAYSTACK_SECRET_KEY is not configured.');
        }

        return Http::baseUrl(config('nis.paystack.base_url'))->withToken($secret)->acceptJson()->timeout(20);
    }
}

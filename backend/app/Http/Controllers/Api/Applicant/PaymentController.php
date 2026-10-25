<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Models\Payment;
use App\Services\ApplicationSubmission;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController
{
    public function initialize(Request $request, PaymentGatewayManager $gateways, ApplicationSubmission $submission): JsonResponse
    {
        $ack = $request->validate([
            'acknowledged_non_refundable' => ['required', 'accepted'],
            'acknowledgement_text' => ['nullable', 'string'],
            'acknowledged_at' => ['nullable', 'string'],
            'version' => ['nullable', 'string'],
            'gateway' => ['nullable', 'string', 'in:remita,paystack'],
        ], [
            'acknowledged_non_refundable.required' => 'You must acknowledge that the application processing fee is non-refundable before proceeding.',
            'acknowledged_non_refundable.accepted' => 'You must acknowledge that the application processing fee is non-refundable before proceeding.',
        ]);

        $applicant = $request->user();
        $submission->assertPrePaymentValid($applicant);

        $draft = $applicant->draft()->first();
        $hasCod = ! empty($draft?->data['has_cod']);
        $feeKobo = $submission->feeKobo($draft?->data, $hasCod);

        $gatewayName = $request->input('gateway') ?: config('nis.payment_gateway', 'paystack');

        try {
            $gateway = $gateways->resolve($gatewayName);

            $result = $gateway->initialize(
                $applicant,
                $feeKobo,
                config('nis.frontend_url').'/portal/apply?payment=callback',
            );
        } catch (Throwable $e) {
            Log::error('Payment initialization error', [
                'gateway' => $gatewayName,
                'applicant_id' => $applicant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Payment initialization failed: '.$e->getMessage(),
                'gateway' => $gatewayName,
            ], 502);
        }

        $payment = $result['payment'];
        $gatewayResponse = $payment->gateway_response ?? [];
        $gatewayResponse['fee_breakdown'] = [
            'normal_fee_naira' => (int) config('nis.fee_naira', 35000),
            'has_cod' => $hasCod,
            'cod_fee_naira' => $hasCod ? (int) config('nis.cod_fee_naira', 15000) : 0,
            'total_amount_naira' => $feeKobo / 100,
        ];
        $gatewayResponse['non_refundable_acknowledgement'] = [
            'acknowledged' => true,
            'acknowledged_at' => $ack['acknowledged_at'] ?? now()->toIso8601String(),
            'text' => $ack['acknowledgement_text'] ?? 'I understand that the application processing fee is generally non-refundable once payment has been successfully processed, except where a refund is required by applicable law or the officially published refund policy.',
            'version' => $ack['version'] ?? '1.0',
            'applicant_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        $payment->gateway_response = $gatewayResponse;
        $payment->save();

        return response()->json([
            'reference' => $result['payment']->reference,
            'amount_naira' => $result['payment']->amount_kobo / 100,
            'authorization_url' => $result['authorization_url'],
            'access_code' => $result['access_code'],
            'public_key' => $result['public_key'],
            'rrr' => $result['rrr'] ?? null,
            'gateway' => $result['gateway'] ?? strtolower($payment->provider ?: 'remita'),
            'fake' => $result['fake'],
        ], 201);
    }

    public function verify(Request $request, string $reference, PaymentGatewayManager $gateways): JsonResponse
    {
        $payment = Payment::where('reference', $reference)
            ->where('applicant_id', $request->user()->id)
            ->firstOrFail();

        try {
            $gateway = $gateways->forPayment($payment);
            $payment = $gateway->verify($payment);
        } catch (Throwable $e) {
            Log::error('Payment verification error', [
                'reference' => $reference,
                'applicant_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'reference' => $payment->reference,
                'status' => $payment->status,
                'paid' => false,
                'used' => $payment->application_id !== null,
                'message' => 'Payment verification failed: '.$e->getMessage(),
                'gateway' => strtolower($payment->provider ?: 'paystack'),
            ], 502);
        }

        return response()->json([
            'reference' => $payment->reference,
            'status' => $payment->status,
            'paid' => $payment->isSuccessful(),
            'used' => $payment->application_id !== null,
            'rrr' => $payment->gateway_response['RRR'] ?? $payment->gateway_response['rrr'] ?? null,
            'gateway' => strtolower($payment->provider ?: 'remita'),
        ]);
    }
}

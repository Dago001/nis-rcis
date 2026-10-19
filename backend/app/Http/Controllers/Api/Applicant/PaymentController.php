<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Models\Payment;
use App\Services\ApplicationSubmission;
use App\Services\PaystackGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController
{
    public function initialize(Request $request, PaystackGateway $gateway, ApplicationSubmission $submission): JsonResponse
    {
        $ack = $request->validate([
            'acknowledged_non_refundable' => ['required', 'accepted'],
            'acknowledgement_text' => ['nullable', 'string'],
            'acknowledged_at' => ['nullable', 'string'],
            'version' => ['nullable', 'string'],
        ], [
            'acknowledged_non_refundable.required' => 'You must acknowledge that the application processing fee is non-refundable before proceeding.',
            'acknowledged_non_refundable.accepted' => 'You must acknowledge that the application processing fee is non-refundable before proceeding.',
        ]);

        $applicant = $request->user();
        $submission->assertPrePaymentValid($applicant);

        $draft = $applicant->draft()->first();
        $hasCod = ! empty($draft?->data['has_cod']);
        $feeKobo = $submission->feeKobo($draft?->data, $hasCod);

        $result = $gateway->initialize(
            $applicant,
            $feeKobo,
            config('nis.frontend_url').'/portal/apply?payment=callback',
        );

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
            'fake' => $result['fake'],
        ], 201);
    }

    public function verify(Request $request, string $reference, PaystackGateway $gateway): JsonResponse
    {
        $payment = Payment::where('reference', $reference)
            ->where('applicant_id', $request->user()->id)
            ->firstOrFail();

        $payment = $gateway->verify($payment);

        return response()->json([
            'reference' => $payment->reference,
            'status' => $payment->status,
            'paid' => $payment->isSuccessful(),
            'used' => $payment->application_id !== null,
        ]);
    }
}

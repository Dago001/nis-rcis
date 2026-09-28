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
        $result = $gateway->initialize(
            $request->user(),
            $submission->feeKobo(),
            config('nis.frontend_url').'/portal/apply?payment=callback',
        );

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

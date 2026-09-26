<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundDecided;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fee refunds: the applicant asks, a Super Administrator decides, and an
 * approved refund is sent back through Paystack.
 *
 * Eligible: a confirmed payment that was never used for an application,
 * or whose application was rejected.
 */
class RefundService
{
    public function __construct(private readonly PaystackGateway $paystack) {}

    public function eligible(Payment $payment): bool
    {
        if (! $payment->isSuccessful()) {
            return false;
        }
        if (Refund::where('payment_id', $payment->id)->whereNotIn('status', ['REJECTED'])->exists()) {
            return false;
        }

        return $payment->application_id === null || $payment->application?->status === ApplicationStatus::Rejected;
    }

    public function request(Applicant $applicant, Payment $payment, string $reason): Refund
    {
        abort_unless($payment->applicant_id === $applicant->id, 404);
        if (! $this->eligible($payment)) {
            throw ValidationException::withMessages(['payment_reference' => 'This payment cannot be refunded: only unused payments or payments for rejected applications are refundable, once.']);
        }

        $refund = Refund::create([
            'payment_id' => $payment->id, 'applicant_id' => $applicant->id, 'application_id' => $payment->application_id,
            'amount_kobo' => $payment->amount_kobo, 'reason' => $reason, 'status' => 'REQUESTED',
        ]);
        Audit::log('REFUND_REQUESTED', "Refund requested for payment {$payment->reference}", $refund, ['amount_kobo' => $payment->amount_kobo], $applicant);

        return $refund;
    }

    public function approve(Refund $refund, User $officer, ?string $notes = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $officer, $notes) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($refund->status, ['REQUESTED', 'FAILED'], true), 422, 'This refund has already been decided.');

            $result = $this->paystack->refund($refund->payment);
            $refund->fill([
                'status' => $result['ok'] ? 'PROCESSED' : 'FAILED',
                'decided_by' => $officer->id,
                'decided_at' => now(),
                'decision_notes' => $notes ?? $result['message'],
                'gateway_reference' => $result['reference'],
                'processed_at' => $result['ok'] ? now() : null,
            ])->save();

            if ($result['ok']) {
                // A refunded payment can never be used for an application again.
                $refund->payment->forceFill(['status' => 'REFUNDED'])->save();
            }
            Audit::log($result['ok'] ? 'REFUND_PROCESSED' : 'REFUND_FAILED', "Refund for payment {$refund->payment->reference}: {$result['message']}", $refund, [], $officer);

            return $refund;
        });

        $refund->applicant->notify(new RefundDecided($refund));

        return $refund;
    }

    public function reject(Refund $refund, User $officer, string $notes): Refund
    {
        abort_unless($refund->status === 'REQUESTED', 422, 'This refund has already been decided.');
        $refund->update(['status' => 'REJECTED', 'decided_by' => $officer->id, 'decided_at' => now(), 'decision_notes' => $notes]);
        Audit::log('REFUND_REJECTED', "Refund for payment {$refund->payment->reference} rejected", $refund, ['notes' => $notes], $officer);
        $refund->applicant->notify(new RefundDecided($refund));

        return $refund;
    }
}

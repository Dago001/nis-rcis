<?php

namespace App\Services;

use App\Enums\StaffRole;
use App\Models\ApprovalRequest;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Two-person rule for sensitive card actions: one officer requests, a
 * different officer with the right role approves, and only then is the
 * change made (through CardIssuance, as always).
 */
class TwoPersonRule
{
    public function __construct(private readonly CardIssuance $issuance) {}

    /** Roles allowed to approve each action (SuperAdmin always may). */
    public static function approverRoles(string $action): array
    {
        return match ($action) {
            ApprovalRequest::REVOKE => [StaffRole::ApprovingOfficer],
            // Requested by a Super Administrator; a second Super Administrator or an Approving Officer approves.
            ApprovalRequest::REINSTATE => [StaffRole::ApprovingOfficer],
            ApprovalRequest::UPDATE => [StaffRole::ApprovingOfficer, StaffRole::IssuingOfficer],
            default => [StaffRole::SuperAdmin],
        };
    }

    public function request(string $action, ResidenceCard $card, User $officer, string $reason, array $payload = []): ApprovalRequest
    {
        if (ApprovalRequest::where('card_id', $card->id)->where('action', $action)->where('status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['action' => 'The same request for this card is already waiting for approval.']);
        }

        $request = ApprovalRequest::create([
            'action' => $action, 'card_id' => $card->id, 'payload' => $payload ?: null,
            'reason' => $reason, 'requested_by' => $officer->id, 'status' => 'PENDING',
        ]);
        Audit::log('APPROVAL_REQUESTED', "{$request->label()} requested for card {$card->card_number}", $card, ['request_id' => $request->id, 'reason' => $reason], $officer);

        return $request;
    }

    public function approve(ApprovalRequest $request, User $approver, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approver, $notes) {
            $request = ApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertCanDecide($request, $approver);

            $card = $request->card;
            $requester = $request->requester;
            match ($request->action) {
                ApprovalRequest::REVOKE => $this->issuance->revoke($card, $approver, $request->reason),
                ApprovalRequest::REINSTATE => $this->issuance->reinstate($card, $approver),
                ApprovalRequest::UPDATE => $this->issuance->update($card, $approver, $request->payload ?? []),
            };

            $request->update(['status' => 'APPROVED', 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);
            Audit::log('APPROVAL_GRANTED', "{$request->label()} for card {$card->card_number} approved (requested by {$requester->auditLabel()})", $card, ['request_id' => $request->id], $approver);

            return $request;
        });
    }

    public function reject(ApprovalRequest $request, User $approver, string $notes): ApprovalRequest
    {
        $this->assertCanDecide($request, $approver);
        $request->update(['status' => 'REJECTED', 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);
        Audit::log('APPROVAL_REJECTED', "{$request->label()} for card {$request->card->card_number} rejected", $request->card, ['request_id' => $request->id, 'notes' => $notes], $approver);

        return $request;
    }

    public function cancel(ApprovalRequest $request, User $officer): ApprovalRequest
    {
        abort_unless($request->status === 'PENDING', 422, 'This request has already been decided.');
        abort_unless($request->requested_by === $officer->id || $officer->role === StaffRole::SuperAdmin, 403, 'Only the requesting officer can cancel it.');
        $request->update(['status' => 'CANCELLED', 'decided_by' => $officer->id, 'decided_at' => now()]);
        Audit::log('APPROVAL_CANCELLED', "{$request->label()} request cancelled", $request->card, ['request_id' => $request->id], $officer);

        return $request;
    }

    public static function canDecide(ApprovalRequest $request, User $user): bool
    {
        return $request->status === 'PENDING'
            && $request->requested_by !== $user->id
            && $user->hasRole(...self::approverRoles($request->action));
    }

    private function assertCanDecide(ApprovalRequest $request, User $approver): void
    {
        abort_unless($request->status === 'PENDING', 422, 'This request has already been decided.');
        abort_if($request->requested_by === $approver->id, 403, 'A different officer must approve your own request (two-person rule).');
        abort_unless($approver->hasRole(...self::approverRoles($request->action)), 403, 'Your role cannot approve this request.');
    }
}

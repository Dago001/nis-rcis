<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\TwoPersonRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Requests waiting for a second officer (two-person rule).
 */
class ApprovalController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'ALL'])]]);
        /** @var User $user */
        $user = $request->user();

        $query = ApprovalRequest::with(['card:id,card_number,surname,forenames,status', 'requester:id,fullname,service_number', 'decider:id,fullname,service_number'])->latest('id');
        if (($data['status'] ?? 'PENDING') !== 'ALL') {
            $query->where('status', $data['status'] ?? 'PENDING');
        }

        return response()->json(['data' => $query->limit(200)->get()->map(fn (ApprovalRequest $r) => $this->present($r, $user))]);
    }

    public function approve(Request $request, int $id, TwoPersonRule $rule): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $approval = $rule->approve(ApprovalRequest::findOrFail($id), $request->user(), $data['notes'] ?? null);

        return response()->json(['data' => $this->present($approval->fresh(['card', 'requester', 'decider']), $request->user())]);
    }

    public function reject(Request $request, int $id, TwoPersonRule $rule): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']]);
        $approval = $rule->reject(ApprovalRequest::findOrFail($id), $request->user(), $data['notes']);

        return response()->json(['data' => $this->present($approval->fresh(['card', 'requester', 'decider']), $request->user())]);
    }

    public function cancel(Request $request, int $id, TwoPersonRule $rule): JsonResponse
    {
        $approval = $rule->cancel(ApprovalRequest::findOrFail($id), $request->user());

        return response()->json(['data' => $this->present($approval->fresh(['card', 'requester', 'decider']), $request->user())]);
    }

    private function present(ApprovalRequest $r, User $user): array
    {
        return [
            'id' => $r->id,
            'action' => $r->action,
            'label' => $r->label(),
            'status' => $r->status,
            'reason' => $r->reason,
            'payload' => $r->payload,
            'card' => $r->card ? ['id' => $r->card->id, 'card_number' => $r->card->card_number, 'holder' => "{$r->card->surname}, {$r->card->forenames}", 'status' => $r->card->status->value] : null,
            'requested_by' => $r->requester?->fullname.' ('.$r->requester?->service_number.')',
            'requested_at' => $r->created_at?->toIso8601String(),
            'decided_by' => $r->decider ? $r->decider->fullname.' ('.$r->decider->service_number.')' : null,
            'decided_at' => $r->decided_at?->toIso8601String(),
            'decision_notes' => $r->decision_notes,
            'can_decide' => TwoPersonRule::canDecide($r, $user),
            'can_cancel' => $r->status === 'PENDING' && $r->requested_by === $user->id,
        ];
    }
}

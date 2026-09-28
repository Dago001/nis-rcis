<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Refund requests (Super Administrators decide).
 */
class RefundController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['REQUESTED', 'PROCESSED', 'REJECTED', 'FAILED', 'ALL'])]]);
        $query = Refund::with(['payment:id,reference,paid_at,channel', 'applicant:id,surname,forenames,email', 'application:id,application_number,status', 'decider:id,fullname,service_number'])->latest('id');
        if (($data['status'] ?? 'REQUESTED') !== 'ALL') {
            $query->where('status', $data['status'] ?? 'REQUESTED');
        }

        return response()->json(['data' => $query->limit(200)->get()->map(fn (Refund $r) => [
            'id' => $r->id,
            'status' => $r->status,
            'amount_naira' => $r->amount_kobo / 100,
            'reason' => $r->reason,
            'payment_reference' => $r->payment?->reference,
            'paid_at' => $r->payment?->paid_at?->toIso8601String(),
            'applicant' => $r->applicant ? "{$r->applicant->surname}, {$r->applicant->forenames} ({$r->applicant->email})" : null,
            'application' => $r->application ? ['id' => $r->application->id, 'application_number' => $r->application->application_number, 'status' => $r->application->status->value] : null,
            'requested_at' => $r->created_at?->toIso8601String(),
            'decided_by' => $r->decider ? "{$r->decider->fullname} ({$r->decider->service_number})" : null,
            'decided_at' => $r->decided_at?->toIso8601String(),
            'decision_notes' => $r->decision_notes,
            'gateway_reference' => $r->gateway_reference,
        ])]);
    }

    public function approve(Request $request, int $id, RefundService $refunds): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $refund = $refunds->approve(Refund::findOrFail($id), $request->user(), $data['notes'] ?? null);

        return response()->json(['status' => $refund->status, 'message' => $refund->status === 'PROCESSED' ? 'Refund sent through Paystack.' : 'Paystack could not process the refund: '.$refund->decision_notes]);
    }

    public function reject(Request $request, int $id, RefundService $refunds): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']]);
        $refunds->reject(Refund::findOrFail($id), $request->user(), $data['notes']);

        return response()->json(['status' => 'REJECTED']);
    }
}

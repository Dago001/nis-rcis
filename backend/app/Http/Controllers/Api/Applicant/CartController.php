<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController
{
    /**
     * Retrieve the authenticated applicant's cart (pending payment items/drafts).
     * Strictly isolated to the logged-in applicant to prevent data leakage.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Applicant $applicant */
        $applicant = $request->user();
        $feeNaira = (int) config('nis.fee_naira', 35000);

        $items = [];

        // 1. Check for active draft pending payment
        $draft = $applicant->draft()->first();
        if ($draft) {
            $draftData = $draft->data ?? [];
            $cat = strtoupper($draftData['category'] ?? 'WORK');
            $catLabel = match ($cat) {
                'BUSINESS' => 'Business (Trade / Artisans / Enterprise)',
                'STUDENT' => 'Student (Intent)',
                'RELIGIOUS' => 'Cleric / Missionaries',
                default => 'Work (Employment)',
            };
            $ref = $draftData['reference_number'] ?? $draftData['application_number'] ?? ('DRAFT-' . str_pad((string) $draft->id, 5, '0', STR_PAD_LEFT));

            // Only show in cart if not already paid
            $paymentRef = $draftData['payment_reference'] ?? null;
            $isPaid = false;
            if ($paymentRef) {
                $isPaid = $applicant->payments()->where('reference', $paymentRef)->where('status', 'SUCCESS')->exists();
            }

            if (! $isPaid) {
                $items[] = [
                    'id' => 'draft-' . $draft->id,
                    'type' => 'DRAFT',
                    'reference' => $ref,
                    'category' => $catLabel,
                    'application_type' => $draft->type ?? 'NEW',
                    'description' => 'ECOWAS Residence Card Application (' . $catLabel . ')',
                    'amount_naira' => $feeNaira,
                    'status' => 'PENDING_PAYMENT',
                    'action_url' => '/portal/apply?resume=1',
                    'created_at' => $draft->created_at?->toIso8601String(),
                ];
            }
        }

        // 2. Check for submitted applications awaiting payment
        $pendingApps = $applicant->applications()
            ->where('payment_status', 'PENDING')
            ->get();

        foreach ($pendingApps as $app) {
            $catLabel = match (strtoupper($app->category ?? '')) {
                'BUSINESS' => 'Business (Trade / Artisans / Enterprise)',
                'STUDENT' => 'Student (Intent)',
                'RELIGIOUS' => 'Cleric / Missionaries',
                default => 'Work (Employment)',
            };
            $amount = $app->fee_amount_kobo > 0 ? (int) ($app->fee_amount_kobo / 100) : $feeNaira;

            $items[] = [
                'id' => 'app-' . $app->id,
                'type' => 'APPLICATION',
                'reference' => $app->reference_number ?: $app->application_number,
                'category' => $catLabel,
                'application_type' => $app->type?->value ?? $app->type ?? 'NEW',
                'description' => 'ECOWAS Residence Card Application (' . $catLabel . ')',
                'amount_naira' => $amount,
                'status' => 'PENDING_PAYMENT',
                'action_url' => '/portal/applications/' . $app->id,
                'created_at' => $app->created_at?->toIso8601String(),
            ];
        }

        $totalNaira = array_sum(array_column($items, 'amount_naira'));

        return response()->json([
            'items' => $items,
            'total_naira' => $totalNaira,
            'currency' => 'NGN',
            'count' => count($items),
        ]);
    }
}

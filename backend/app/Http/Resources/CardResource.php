<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\ResidenceCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ResidenceCard */
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'card_number' => $this->card_number,
            'booklet_number' => $this->booklet_number,
            'status' => $this->status->value,
            'verification_status' => $this->verificationStatus(),
            'issuing_country' => $this->issuing_country,
            'statutory_protocol' => $this->statutory_protocol,
            'decision_reference' => $this->decision_reference,
            'decision_date' => $this->decision_date?->toDateString(),
            'approving_authority' => $this->approving_authority,
            ...$this->only(Application::PARTICULARS),
            'issuing_officer_name' => $this->issuing_officer_name,
            'issuing_officer_service_no' => $this->issuing_officer_service_no,
            'issued_on' => $this->issued_on?->toDateString(),
            'issued_at' => $this->issued_at,
            'expires_on' => $this->expires_on?->toDateString(),
            'postage_stamp_code' => $this->postage_stamp_code,
            'authority_signature' => $this->authority_signature,
            'query_reason' => $this->query_reason,
            'revocation_reason' => $this->revocation_reason,
            'revoked_at' => $this->revoked_at,
            'is_watchlisted' => $this->is_watchlisted,
            'watchlist_reason' => $this->watchlist_reason,
            'watchlisted_at' => $this->watchlisted_at,
            'approved_at' => $this->approved_at,
            'reported_lost_at' => $this->reported_lost_at,
            'lost_report_type' => $this->lost_report_type,
            'lost_report_details' => $this->lost_report_details,
            'police_report_number' => $this->police_report_number,
            'application_id' => $this->whenLoaded('application', fn () => $this->application?->id),
            'renewals' => $this->whenLoaded('renewals', fn () => $this->renewals->map(fn ($r) => [
                'renewal_number' => $r->renewal_number,
                'from_date' => $r->from_date->toDateString(),
                'to_date' => $r->to_date->toDateString(),
                'renewed_at' => $r->renewed_at,
                'endorsing_officer' => $r->endorsing_officer,
                'officer_service_no' => $r->officer_service_no,
                'fee_paid_naira' => $r->fee_paid_kobo / 100,
                'receipt_number' => $r->receipt_number,
                'remarks' => $r->remarks,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}

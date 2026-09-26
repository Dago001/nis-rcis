<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Application */
class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'reference_number' => $this->reference_number,
            'type' => $this->type,
            'channel' => $this->channel,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'tracker_step' => $this->status->trackerStep(),
            ...$this->only(Application::PARTICULARS),
            'phone' => $this->phone,
            'email' => $this->email,
            'enrollment_center' => $this->whenLoaded('enrollmentCenter', fn () => $this->enrollmentCenter->only(['id', 'code', 'name', 'state', 'address'])),
            'appointment_date' => $this->appointment_date?->toDateString(),
            'appointment_time' => $this->appointment_time,
            'fee_amount_naira' => $this->fee_amount_kobo / 100,
            'payment_status' => $this->payment_status,
            // Fraud/duplicate flags are for officers only, never the applicant.
            'risk_flags' => $this->when($request->user() instanceof User, fn () => $this->risk_flags ?? []),
            'submitted_at' => $this->submitted_at,
            'decided_at' => $this->decided_at,
            'decision_notes' => $this->decision_notes,
            'biometrics_captured_at' => $this->biometrics_captured_at,
            'ready_at' => $this->ready_at,
            'collected_at' => $this->collected_at,
            'card' => $this->whenLoaded('card', fn () => $this->card?->only(['id', 'card_number', 'booklet_number', 'expires_on'])),
            'renewal_of_card_number' => $this->whenLoaded('renewalOfCard', fn () => $this->renewalOfCard?->card_number),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type->value,
                'label' => $d->type->label(),
                'mime_type' => $d->mime_type,
                'size_bytes' => $d->size_bytes,
                'version' => $d->version,
                'uploaded_at' => $d->created_at,
            ])->values()),
            'history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'label' => $h->to_status->label(),
                'notes' => $h->notes,
                'at' => $h->created_at,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}

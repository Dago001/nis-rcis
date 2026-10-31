<?php

namespace App\Http\Resources;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use App\Support\WorkingDays;
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
            'category' => $this->category,
            'channel' => $this->channel,
            'status' => (! ($request->user() instanceof User) && $this->status === ApplicationStatus::Vetted) ? ApplicationStatus::PendingApproval->value : $this->status->value,
            'status_label' => $this->status->label($request->user() instanceof User),
            'vetting_status' => $this->vetting_status,
            'vetting_status_label' => $this->vetting_status_label,
            'biometrics_status' => $this->biometrics_status,
            'biometrics_status_label' => $this->biometrics_status_label,
            'approval_status' => $this->approval_status,
            'approval_status_label' => $this->approval_status_label,
            'tracker_step' => $this->status->trackerStep(),
            ...$this->only(Application::PARTICULARS),
            'phone' => $this->phone,
            'email' => $this->email,
            'enrollment_center' => $this->whenLoaded('enrollmentCenter', fn () => [
                'id' => $this->enrollmentCenter->id,
                'code' => $this->enrollmentCenter->code,
                'name' => $this->enrollmentCenter->name,
                'state' => $this->enrollmentCenter->state,
                'address' => $this->enrollmentCenter->address,
                'zone_id' => $this->enrollmentCenter->zone_id,
                'zone_code' => $this->enrollmentCenter->zone_code,
                'zone_name' => $this->enrollmentCenter->zone_name,
                'command_name' => $this->enrollmentCenter->command_name,
                'is_biometric_enabled' => (bool) $this->enrollmentCenter->is_biometric_enabled,
                'is_booking_enabled' => (bool) $this->enrollmentCenter->is_booking_enabled,
            ]),
            'actual_enrollment_center' => $this->actual_enrollment_center_id ? [
                'id' => $this->actual_enrollment_center_id,
                'command_name' => $this->actual_enrollment_command ?? $this->actualEnrollmentCenter?->command_name ?? $this->actualEnrollmentCenter?->name,
                'zone_name' => $this->actual_enrollment_zone ?? $this->actualEnrollmentCenter?->zone_name,
                'state' => $this->actual_enrollment_state ?? $this->actualEnrollmentCenter?->state,
            ] : null,
            'production_location' => 'NIS Service Headquarters',
            'appointment_date' => $this->appointment_date?->toDateString(),
            'appointment_time' => $this->appointment_time,
            'fee_amount_naira' => $this->fee_amount_kobo / 100,
            'payment_status' => $this->payment_status,
            'has_cod' => (bool) $this->has_cod,
            'cod_fee_naira' => $this->cod_fee_kobo ? $this->cod_fee_kobo / 100 : 0,
            'cod_changes' => $this->cod_changes,
            'cod_status' => $this->cod_status,
            'renewal_source_application_id' => $this->renewal_source_application_id,
            // Fraud/duplicate flags are for officers only, never the applicant.
            'risk_flags' => $this->when($request->user() instanceof User, fn () => $this->risk_flags ?? []),
            // Internal assignment and the service-level clock are for officers only.
            'quota_reference' => $this->quota_reference,
            'employer_name' => $this->employer_name,
            // Which fingers were captured (never the templates themselves).
            'fingerprints_captured' => $this->when($request->user() instanceof User, fn () => collect($this->fingerprints ?? [])->pluck('finger')->values()),
            'assigned_to' => $this->when($request->user() instanceof User, fn () => $this->relationLoaded('assignee') ? $this->assignee?->only(['id', 'fullname', 'service_number']) : null),
            'sla' => $this->when($request->user() instanceof User && $this->status === ApplicationStatus::PendingApproval && $this->submitted_at, fn () => [
                'working_days' => $days = WorkingDays::between($this->submitted_at),
                'target' => (int) config('nis.sla_working_days'),
                'overdue' => $days > config('nis.sla_working_days'),
            ]),
            'submitted_at' => $this->submitted_at,
            'vetted_at' => $this->when($request->user() instanceof User, fn () => $this->vetted_at),
            'vetting_notes' => $this->when($request->user() instanceof User, fn () => $this->vetting_notes),
            'vetted_by' => $this->when($request->user() instanceof User, fn () => $this->relationLoaded('vettedBy') ? $this->vettedBy?->only(['id', 'fullname', 'service_number']) : null),
            'decided_at' => $this->decided_at,
            'decision_notes' => $this->decision_notes,
            'decided_by' => $this->when($request->user() instanceof User, fn () => $this->relationLoaded('decidedBy') ? $this->decidedBy?->only(['id', 'fullname', 'service_number']) : null),
            'biometrics_captured_at' => $this->biometrics_captured_at,
            'biometrics_captured_by' => $this->when($request->user() instanceof User, fn () => $this->relationLoaded('biometricsCapturedBy') ? $this->biometricsCapturedBy?->only(['id', 'fullname', 'service_number']) : null),
            'ready_at' => $this->ready_at,
            'collected_at' => $this->collected_at,
            'card' => $this->whenLoaded('card', fn () => $this->card?->only(['id', 'card_number', 'booklet_number', 'expires_on'])),
            'principal_application_id' => $this->principal_application_id,
            'principal_application_number' => $this->principal?->application_number,
            'dependant_relationship' => $this->dependant_relationship,
            'principal' => $this->whenLoaded('principal', fn () => $this->principal?->only(['id', 'application_number', 'surname', 'forenames'])),
            'dependants' => $this->whenLoaded('dependants', fn () => $this->dependants->map(fn ($d) => [
                'id' => $d->id, 'application_number' => $d->application_number, 'surname' => $d->surname,
                'forenames' => $d->forenames, 'relationship' => $d->dependant_relationship, 'status' => $d->status->value,
            ])->values()),
            'renewal_of_card_number' => $this->whenLoaded('renewalOfCard', fn () => $this->renewalOfCard?->card_number),
            'documents' => $this->whenLoaded('documents', function () {
                $queries = $this->relationLoaded('documentQueries')
                    ? $this->documentQueries
                    : $this->documentQueries()->with(['queriedBy:id,fullname,service_number', 'resolvedBy:id,fullname,service_number'])->get();
                $queriesByDoc = $queries->groupBy('document_id');

                return $this->documents->map(function ($d) use ($queriesByDoc) {
                    $docQueries = $queriesByDoc->get($d->id, collect());
                    $activeQuery = $docQueries->firstWhere('status', '!=', \App\Models\ApplicationDocumentQuery::STATUS_RESOLVED);

                    return [
                        'id' => $d->id,
                        'type' => $d->type->value,
                        'label' => $d->type->label(),
                        'mime_type' => $d->mime_type,
                        'size_bytes' => $d->size_bytes,
                        'version' => $d->version,
                        'uploaded_at' => $d->created_at,
                        'active_query' => $activeQuery ? [
                            'id' => $activeQuery->id,
                            'query_reason' => $activeQuery->query_reason,
                            'stage' => $activeQuery->stage,
                            'status' => $activeQuery->status,
                            'status_label' => $activeQuery->status_label,
                            'queried_at' => $activeQuery->queried_at?->toIso8601String(),
                            'queried_by' => $activeQuery->queriedBy ? [
                                'fullname' => $activeQuery->queriedBy->fullname,
                                'service_number' => $activeQuery->queriedBy->service_number,
                            ] : null,
                            'applicant_response' => $activeQuery->applicant_response,
                            'responded_at' => $activeQuery->responded_at?->toIso8601String(),
                        ] : null,
                        'queries' => $docQueries->map(fn ($q) => [
                            'id' => $q->id,
                            'query_reason' => $q->query_reason,
                            'stage' => $q->stage,
                            'status' => $q->status,
                            'status_label' => $q->status_label,
                            'queried_at' => $q->queried_at?->toIso8601String(),
                            'applicant_response' => $q->applicant_response,
                            'responded_at' => $q->responded_at?->toIso8601String(),
                            'resolved_at' => $q->resolved_at?->toIso8601String(),
                        ])->values(),
                    ];
                })->values();
            }),
            'document_queries' => $this->whenLoaded('documentQueries', fn () => $this->documentQueries->map(fn ($q) => [
                'id' => $q->id,
                'document_id' => $q->document_id,
                'document_type' => $q->document_type,
                'document_label' => $q->document?->type?->label() ?? $q->document_type,
                'stage' => $q->stage,
                'query_reason' => $q->query_reason,
                'status' => $q->status,
                'status_label' => $q->status_label,
                'queried_at' => $q->queried_at?->toIso8601String(),
                'queried_by' => $q->queriedBy ? [
                    'fullname' => $q->queriedBy->fullname,
                    'service_number' => $q->queriedBy->service_number,
                ] : null,
                'applicant_response' => $q->applicant_response,
                'responded_at' => $q->responded_at?->toIso8601String(),
                'resolved_at' => $q->resolved_at?->toIso8601String(),
                'resolution_notes' => $q->resolution_notes,
            ])->values()),
            'unresolved_document_queries_count' => $this->relationLoaded('documentQueries')
                ? $this->documentQueries->where('status', '!=', \App\Models\ApplicationDocumentQuery::STATUS_RESOLVED)->count()
                : $this->unresolvedDocumentQueries()->count(),
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

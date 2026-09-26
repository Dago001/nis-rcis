<?php

namespace App\Http\Controllers\Api\Staff;

use App\Models\Application;
use App\Models\Payment;
use App\Services\DocumentStorage;
use App\Services\PaystackGateway;
use App\Support\Audit;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Residence card fee payments, visible to staff as soon as Paystack
 * confirms them, including applicants who have paid but not yet finished
 * (booked biometrics and signed the declaration of) their application.
 */
class PaymentController
{
    /** Draft fields shown to staff for an application still in progress. */
    private const DRAFT_FIELDS = [
        'surname', 'forenames', 'nationality', 'sex', 'date_of_birth', 'place_of_birth', 'profession',
        'passport_number', 'passport_issue_date', 'passport_expiry', 'national_id_number',
        'domicile', 'domicile_lga', 'domicile_state', 'phone', 'email',
        'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
        'emergency_contact_address', 'emergency_contact_lga', 'emergency_contact_state',
        'appointment_date', 'appointment_time', 'renewal_card_number',
    ];

    private const STEPS = [
        1 => 'Personal details', 2 => 'Passport', 3 => 'Residence & contacts', 4 => 'Documents',
        5 => 'Fee payment', 6 => 'Biometrics appointment', 7 => 'Review & declaration',
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['awaiting_submission', 'submitted', 'failed', 'all'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Payment::query()->with(['applicant.draft', 'application'])->latest('id');

        match ($data['filter'] ?? 'awaiting_submission') {
            'awaiting_submission' => $this->paid($query)->whereNull('application_id'),
            'submitted' => $this->paid($query)->whereNotNull('application_id'),
            'failed' => $query->where('status', 'FAILED'),
            'all' => $query->where('status', '!=', 'INITIALIZED'),
        };

        if (! empty($data['search'])) {
            $term = trim($data['search']);
            $query->where(fn (Builder $q) => $q->where('reference', strtoupper($term))
                ->orWhereHas('applicant', fn (Builder $a) => $a->where('email', 'ilike', Like::contains($term))
                    ->orWhere('surname', 'ilike', Like::contains($term))
                    ->orWhere('forenames', 'ilike', Like::contains($term))));
        }

        $page = $query->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Payment $p) => $this->summary($p))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * One payment, with the applicant's in-progress application if it has
     * not been submitted yet.
     */
    public function show(int $id, DocumentStorage $storage): JsonResponse
    {
        $payment = Payment::with(['applicant.draft.documents', 'application'])->findOrFail($id);
        $draft = $payment->application_id ? null : $payment->applicant?->draft;

        Audit::log('PAYMENT_VIEWED', "Payment {$payment->reference} viewed", $payment);

        return response()->json([
            ...$this->summary($payment),
            'receipt' => $payment->isSuccessful() ? PaystackGateway::receipt($payment) : null,
            'draft_data' => $draft ? collect($draft->data)->only(self::DRAFT_FIELDS) : null,
            'draft_documents' => $draft ? $draft->documents->map(fn ($d) => [
                'id' => $d->id,
                'label' => $d->type->label(),
                'mime_type' => $d->mime_type,
                'size_bytes' => $d->size_bytes,
                'url' => $storage->temporaryUrl($d),
            ])->values() : [],
        ]);
    }

    private function paid(Builder $query): Builder
    {
        return $query->where('status', 'SUCCESS')->whereNotNull('verified_at');
    }

    private function summary(Payment $p): array
    {
        $applicant = $p->applicant;
        $draft = $applicant?->draft;
        /** @var Application|null $application */
        $application = $p->application;
        $step = $draft ? (int) $draft->current_step : null;

        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'amount_naira' => $p->amount_kobo / 100,
            'status' => $p->isSuccessful() ? 'PAID' : $p->status,
            'channel' => $p->channel,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'applicant' => $applicant ? [
                'name' => trim("{$applicant->surname}, {$applicant->forenames}", ', '),
                'email' => $applicant->email,
                'phone' => $applicant->phone,
            ] : null,
            'application' => $application ? [
                'id' => $application->id,
                'application_number' => $application->application_number,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
            ] : null,
            'progress' => ! $application && $draft && $p->isSuccessful() ? [
                'current_step' => $step,
                'step_label' => self::STEPS[$step] ?? null,
                'nationality' => $draft->data['nationality'] ?? null,
                'passport_number' => $draft->data['passport_number'] ?? null,
                'saved_at' => $draft->updated_at?->toIso8601String(),
            ] : null,
        ];
    }
}

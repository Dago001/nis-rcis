<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Http\Resources\ApplicationResource;
use App\Models\Applicant;
use App\Models\Application;
use App\Services\ApplicationSubmission;
use App\Services\ApplicationWorkflow;
use App\Services\DocumentStorage;
use App\Services\PaystackGateway;
use App\Services\PhotoQuality;
use App\Support\ApplicationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $applications = $this->applicant($request)->applications()
            ->with(['enrollmentCenter', 'card', 'documents'])
            ->latest('id')
            ->get();

        return ApplicationResource::collection($applications);
    }

    public function show(Request $request, int $id): ApplicationResource
    {
        return new ApplicationResource(
            $this->owned($request, $id)->load(['enrollmentCenter', 'card', 'renewalOfCard', 'principal', 'dependants', 'documents', 'statusHistory'])
        );
    }

    /**
     * Final submission of the wizard (step 7: declaration).
     */
    public function store(Request $request, ApplicationSubmission $submission): JsonResponse
    {
        $applicant = $this->applicant($request);
        if (! $request->filled('type')) {
            $draft = $applicant->draft()->first();
            $request->merge(['type' => $draft?->type ?: 'NEW']);
        }
        if (! $request->filled('category')) {
            $draft = $applicant->draft()->with('documents')->first();
            $draftCat = strtoupper($draft?->data['category'] ?? '');
            if (! in_array($draftCat, ['WORK', 'BUSINESS', 'STUDENT'], true)) {
                if ($draft?->documents->contains('type', DocumentType::AdmissionLetter)) {
                    $draftCat = 'STUDENT';
                } elseif ($draft?->documents->contains('type', DocumentType::CacCertificate)) {
                    $draftCat = 'BUSINESS';
                } elseif ($draft?->documents->contains('type', DocumentType::AppointmentLetter)) {
                    $draftCat = 'WORK';
                } else {
                    $draftCat = 'WORK';
                }
            }
            $request->merge(['category' => $draftCat]);
        }
        $request->merge(ApplicationRules::normalise($request->except(['phone', 'email'])));

        $rules = ApplicationRules::particulars();
        // A dependent child may be any age; everyone else must be an adult.
        if ($request->filled('principal_application_id') && $request->input('dependant_relationship') === 'CHILD') {
            $rules['date_of_birth'] = ['required', 'date', 'before:today', 'after:1900-01-01'];
        }

        $data = $request->validate([
            ...$rules,
            ...ApplicationRules::quota(),
            ...ApplicationRules::appointment(),
            'category' => ['required', Rule::in(['WORK', 'BUSINESS', 'STUDENT'])],
            'type' => ['required', Rule::in(['NEW', 'RENEWAL', 'REPLACE'])],
            'renewal_card_number' => ['required_if:type,RENEWAL,REPLACE', 'nullable', 'string', 'max:20'],
            'principal_application_id' => ['nullable', 'integer'],
            'dependant_relationship' => ['required_with:principal_application_id', 'nullable', Rule::in(['SPOUSE', 'CHILD'])],
            'payment_reference' => ['required', 'string', 'max:64'],
            'declaration' => ['accepted'],
        ]);

        // Phone and e-mail always come from the registered account; they cannot
        // be changed in the application form (and accounts created before the
        // stricter phone format must still be able to apply).
        $data['phone'] = $applicant->phone;
        $data['email'] = $applicant->email;

        $application = $submission->submitOnline($applicant, $data);

        return (new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory'])))
            ->response()->setStatusCode(201);
    }

    /**
     * Re-upload a document while the application is QUERIED.
     */
    public function uploadDocument(Request $request, int $id, DocumentStorage $storage): ApplicationResource
    {
        $application = $this->owned($request, $id);
        $this->assertQueried($application);

        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)->only(DocumentType::applicantUploadable())],
            'file' => ['required', 'file', 'max:'.config('nis.max_upload_kb')],
        ]);

        if ($data['type'] === DocumentType::Photo->value && ($problems = app(PhotoQuality::class)->problems((string) $request->file('file')->get()))) {
            throw ValidationException::withMessages(['file' => $problems]);
        }

        $document = $storage->storeUpload($request->file('file'), DocumentType::from($data['type']), ['application_id' => $application->id], $this->applicant($request));
        if ($document->type === DocumentType::Photo) {
            $application->forceFill(['photo_path' => $document->path])->save();
        }

        return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory']));
    }

    public function update(Request $request, int $id): ApplicationResource
    {
        $application = $this->owned($request, $id);
        $this->assertQueried($application);

        $allowed = [
            'category', 'profession', 'domicile', 'domicile_state', 'domicile_lga',
            'change_of_address', 'passport_number', 'passport_issue_date', 'passport_expiry',
            'national_id_number', 'tax_id_number', 'emergency_contact_name',
            'emergency_contact_relation', 'emergency_contact_phone', 'emergency_contact_address',
            'emergency_contact_state', 'emergency_contact_lga', 'height', 'complexion',
            'eye_color', 'hair_color', 'distinguished_features', 'blood_group',
        ];

        $data = $request->only($allowed);
        if (! empty($data)) {
            $application->forceFill($data)->save();
        }

        return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory']));
    }

    /**
     * Respond to a query: returns the application to the approval queue.
     */
    public function respondToQuery(Request $request, int $id, ApplicationWorkflow $workflow): ApplicationResource
    {
        $application = $this->owned($request, $id);
        $this->assertQueried($application);

        $data = $request->validate(['response' => ['nullable', 'string', 'max:2000']]);

        $applicant = $this->applicant($request);

        $note = !empty($data['response'])
            ? 'Applicant response: '.$data['response']
            : 'Applicant submitted requested document(s) in response to query.';

        $application = $workflow->transition(
            $application,
            ApplicationStatus::PendingApproval,
            $applicant,
            $note,
            function (Application $a) {
                $a->decided_by = null;
                $a->decided_at = null;
            }
        );

        try {
            $applicant->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => \App\Notifications\ApplicationStatusChanged::class,
                'data' => [
                    'application_id' => $application->id,
                    'application_number' => $application->application_number,
                    'reference_number' => $application->reference_number,
                    'status' => 'PENDING_APPROVAL',
                    'title' => 'Query Response Submitted',
                    'notes' => 'Your response to application '.$application->application_number.' has been submitted and is awaiting officer re-vetting.',
                    'action_url' => "/portal/applications/{$application->id}",
                    'action_label' => 'View Application',
                ],
                'read_at' => null,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to create query response notification: '.$e->getMessage());
        }

        return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory']));
    }

    public function document(Request $request, int $id, int $documentId, DocumentStorage $storage): JsonResponse
    {
        $document = $this->owned($request, $id)->documents()->findOrFail($documentId);

        return response()->json(['url' => $storage->temporaryUrl($document), 'expires_in' => config('nis.document_url_ttl_minutes') * 60]);
    }

    /**
     * Data for the printable application slip and biometrics appointment slip.
     */
    public function slip(Request $request, int $id, DocumentStorage $storage): JsonResponse
    {
        $application = $this->owned($request, $id)->load(['enrollmentCenter', 'documents', 'principal']);
        $payment = $application->payments()->where('status', 'SUCCESS')->latest('id')->first();

        return response()->json([
            'application' => new ApplicationResource($application),
            'payment' => $payment ? ['reference' => $payment->reference, 'amount_naira' => $payment->amount_kobo / 100, ...PaystackGateway::receipt($payment)] : null,
            'documents' => $application->documents
                ->reject(fn ($d) => $d->type === DocumentType::Signature)
                ->map(fn ($d) => ['type' => $d->type->value, 'label' => $d->type->label()])->values(),
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'photo_url' => $storage->temporaryUrlForPath($application->photo_path),
            'signature_url' => $storage->temporaryUrlForPath($application->signature_path),
            'qr_payload' => "NIS-RCIS|APP:{$application->application_number}|REF:{$application->reference_number}",
            'appointment_slip_available' => ! in_array($application->status, [
                ApplicationStatus::PendingApproval, ApplicationStatus::Queried, ApplicationStatus::Rejected,
            ], true),
            'temporary_slip_available' => in_array($application->status, [
                ApplicationStatus::BiometricsCaptured, ApplicationStatus::ReadyForCollection, ApplicationStatus::Issued,
            ], true),
            'is_card_produced' => in_array($application->status, [
                ApplicationStatus::ReadyForCollection, ApplicationStatus::Issued,
            ], true) || ($application->card && $application->card->status->isActive()),
        ]);
    }

    private function owned(Request $request, int $id): Application
    {
        return $this->applicant($request)->applications()->findOrFail($id);
    }

    private function assertQueried(Application $application): void
    {
        if ($application->status !== ApplicationStatus::Queried) {
            throw ValidationException::withMessages(['status' => 'Documents can only be changed while the application is queried.']);
        }
    }

    private function applicant(Request $request): Applicant
    {
        return $request->user();
    }
}

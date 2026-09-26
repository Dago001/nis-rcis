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
            ->with(['enrollmentCenter', 'card'])
            ->latest('id')
            ->get();

        return ApplicationResource::collection($applications);
    }

    public function show(Request $request, int $id): ApplicationResource
    {
        return new ApplicationResource(
            $this->owned($request, $id)->load(['enrollmentCenter', 'card', 'renewalOfCard', 'documents', 'statusHistory'])
        );
    }

    /**
     * Final submission of the wizard (step 7: declaration).
     */
    public function store(Request $request, ApplicationSubmission $submission): JsonResponse
    {
        $applicant = $this->applicant($request);
        $request->merge(ApplicationRules::normalise($request->except(['phone', 'email'])));

        $data = $request->validate([
            ...ApplicationRules::particulars(),
            ...ApplicationRules::appointment(),
            'type' => ['required', Rule::in(['NEW', 'RENEWAL'])],
            'renewal_card_number' => ['required_if:type,RENEWAL', 'nullable', 'string', 'max:20'],
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

        $document = $storage->storeUpload($request->file('file'), DocumentType::from($data['type']), ['application_id' => $application->id], $this->applicant($request));
        if ($document->type === DocumentType::Photo) {
            $application->forceFill(['photo_path' => $document->path])->save();
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

        $data = $request->validate(['response' => ['required', 'string', 'max:2000']]);

        $application = $workflow->transition($application, ApplicationStatus::PendingApproval, $this->applicant($request),
            'Applicant response: '.$data['response']);

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
        $application = $this->owned($request, $id)->load(['enrollmentCenter', 'documents']);
        $payment = $application->payments()->where('status', 'SUCCESS')->latest('id')->first();

        return response()->json([
            'application' => new ApplicationResource($application),
            'payment' => $payment ? ['reference' => $payment->reference, 'amount_naira' => $payment->amount_kobo / 100, ...PaystackGateway::receipt($payment)] : null,
            'documents' => $application->documents
                ->reject(fn ($d) => $d->type === DocumentType::Signature)
                ->map(fn ($d) => ['type' => $d->type->value, 'label' => $d->type->label()])->values(),
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'photo_url' => $storage->temporaryUrlForPath($application->photo_path),
            'qr_payload' => "NIS-RCIS|APP:{$application->application_number}|REF:{$application->reference_number}",
            'appointment_slip_available' => ! in_array($application->status, [
                ApplicationStatus::PendingApproval, ApplicationStatus::Queried, ApplicationStatus::Rejected,
            ], true),
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

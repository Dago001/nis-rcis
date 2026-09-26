<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Enums\StaffRole;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\User;
use App\Notifications\ApplicationAssigned;
use App\Services\ApplicationSubmission;
use App\Services\ApplicationWorkflow;
use App\Services\CardIssuance;
use App\Services\DocumentStorage;
use App\Services\RiskChecker;
use App\Support\ApplicationRules;
use App\Support\Audit;
use App\Support\Like;
use App\Support\WorkingDays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Approval queue, assisted applications, biometrics desk and collection
 * (legacy pending-approvals.php, new-card.php, biometrics-capture.php).
 */
class ApplicationController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'search' => ['nullable', 'string', 'max:200'],
            'appointment_date' => ['nullable', 'date'],
            'enrollment_center_id' => ['nullable', 'integer'],
            'assigned' => ['nullable', Rule::in(['me', 'unassigned'])],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $query = Application::query()->with(['enrollmentCenter', 'assignee:id,fullname,service_number'])->latest('id');

        if (($data['assigned'] ?? null) === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif (($data['assigned'] ?? null) === 'unassigned') {
            $query->whereNull('assigned_to');
        }
        if (! empty($data['overdue'])) {
            // Waiting for a decision longer than the service-level target.
            $query->where('status', ApplicationStatus::PendingApproval)
                ->where('submitted_at', '<', WorkingDays::cutoff(config('nis.sla_working_days')));
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['appointment_date'])) {
            $query->whereDate('appointment_date', $data['appointment_date']);
        }
        if (! empty($data['enrollment_center_id'])) {
            $query->where('enrollment_center_id', $data['enrollment_center_id']);
        }
        if (! empty($data['search'])) {
            $term = $this->normaliseSearch($data['search']);
            $query->where(function ($q) use ($term) {
                $q->where('application_number', $term)
                    ->orWhere('reference_number', $term)
                    ->orWhere('passport_number', $term)
                    ->orWhereRaw("replace(application_number, '-', '') = ?", [preg_replace('/[^A-Z0-9]/', '', $term)])
                    ->orWhere('surname', 'ilike', Like::contains($term))
                    ->orWhere('forenames', 'ilike', Like::contains($term));
            });
        }

        return ApplicationResource::collection($query->paginate(25));
    }

    /** Internal notes: visible to staff only, never to the applicant. */
    public function notes(int $id): JsonResponse
    {
        $notes = ApplicationNote::with('author:id,fullname,service_number')->where('application_id', Application::findOrFail($id)->id)->latest('id')->get();

        return response()->json(['data' => $notes->map(fn (ApplicationNote $n) => [
            'id' => $n->id, 'body' => $n->body, 'at' => $n->created_at?->toIso8601String(),
            'author' => $n->author ? "{$n->author->fullname} ({$n->author->service_number})" : null,
        ])]);
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $application = Application::findOrFail($id);
        $note = ApplicationNote::create(['application_id' => $application->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);
        Audit::log('APPLICATION_NOTE_ADDED', "Internal note added to {$application->application_number}", $application);

        return response()->json(['id' => $note->id], 201);
    }

    /** Assign the application to an officer (or clear the assignment). */
    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer']]);
        $application = Application::findOrFail($id);
        $assignee = null;
        if (! empty($data['user_id'])) {
            $assignee = User::where('is_active', true)->findOrFail($data['user_id']);
            abort_unless($assignee->hasRole(StaffRole::ApprovingOfficer, StaffRole::IssuingOfficer), 422, 'Applications can only be assigned to approving or issuing officers.');
        }

        $application->forceFill(['assigned_to' => $assignee?->id, 'assigned_at' => $assignee ? now() : null])->saveQuietly();
        Audit::log('APPLICATION_ASSIGNED', $assignee ? "{$application->application_number} assigned to {$assignee->fullname}" : "{$application->application_number} unassigned", $application);
        if ($assignee && $assignee->id !== $request->user()->id) {
            $assignee->notify(new ApplicationAssigned($application, $request->user()));
        }

        return response()->json(['assigned_to' => $assignee?->only(['id', 'fullname', 'service_number'])]);
    }

    /** Officers an application can be assigned to. */
    public function assignees(): JsonResponse
    {
        return response()->json(['data' => User::where('is_active', true)
            ->whereIn('role', [StaffRole::ApprovingOfficer, StaffRole::IssuingOfficer, StaffRole::SuperAdmin])
            ->orderBy('fullname')->get(['id', 'fullname', 'service_number', 'role'])]);
    }

    /** Re-run the fraud and duplicate checks (e.g. after new documents). */
    public function riskCheck(int $id, RiskChecker $checker): JsonResponse
    {
        $application = Application::findOrFail($id);
        $flags = $checker->check($application);
        Audit::log('APPLICATION_RISK_CHECK', "Fraud and duplicate checks re-run on {$application->application_number}", $application, ['flags' => count($flags)]);

        return response()->json(['risk_flags' => $flags, 'checked_at' => $application->risk_checked_at]);
    }

    public function show(int $id, DocumentStorage $storage): JsonResponse
    {
        $application = Application::with(['enrollmentCenter', 'card', 'renewalOfCard', 'principal', 'dependants', 'documents', 'statusHistory', 'payments', 'assignee:id,fullname,service_number'])->findOrFail($id);

        return response()->json([
            'data' => new ApplicationResource($application),
            'photo_url' => $storage->temporaryUrlForPath($application->photo_path),
            'payments' => $application->payments->map->only(['reference', 'status', 'amount_kobo', 'channel', 'paid_at', 'verified_at']),
        ]);
    }

    /**
     * Assisted (walk-in) application entered by staff. SuperAdmin only,
     * as in the legacy system.
     */
    public function store(Request $request, ApplicationSubmission $submission): JsonResponse
    {
        $request->merge(ApplicationRules::normalise($request->all()));
        $data = $request->validate([
            ...ApplicationRules::particulars(),
            ...ApplicationRules::contact(),
            ...ApplicationRules::appointment(),
            'payment_status' => ['required', Rule::in(['PAID', 'PENDING'])],
        ]);

        $application = $submission->submitAssisted($this->officer($request), $data);

        return (new ApplicationResource($application->load(['enrollmentCenter', 'statusHistory'])))->response()->setStatusCode(201);
    }

    /**
     * Staff upload of supporting documents for an assisted application.
     */
    public function uploadDocument(Request $request, int $id, DocumentStorage $storage): ApplicationResource
    {
        $application = Application::findOrFail($id);
        abort_unless($application->status->isOpen(), 422, 'This application is closed.');

        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)->only(DocumentType::applicantUploadable())],
            'file' => ['required', 'file', 'max:'.config('nis.max_upload_kb')],
        ]);

        $document = $storage->storeUpload($request->file('file'), DocumentType::from($data['type']), ['application_id' => $application->id], $this->officer($request));
        if ($document->type === DocumentType::Photo) {
            $application->forceFill(['photo_path' => $document->path])->save();
        }

        return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory']));
    }

    /**
     * APPROVE (for biometrics) | QUERY | REJECT.
     */
    public function decide(Request $request, int $id, ApplicationWorkflow $workflow): ApplicationResource
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVE', 'QUERY', 'REJECT'])],
            'notes' => ['required_unless:decision,APPROVE', 'nullable', 'string', 'max:2000'],
        ]);

        $officer = $this->officer($request);
        $to = match ($data['decision']) {
            'APPROVE' => ApplicationStatus::ApprovedForBiometrics,
            'QUERY' => ApplicationStatus::Queried,
            'REJECT' => ApplicationStatus::Rejected,
        };
        $notes = $data['notes'] ?? 'Approved for physical biometrics capture at the enrollment center.';

        $application = $workflow->transition(Application::findOrFail($id), $to, $officer, $notes, function (Application $a) use ($officer, $notes) {
            $a->decided_by = $officer->id;
            $a->decided_at = now();
            $a->decision_notes = $notes;
        });

        return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory']));
    }

    public function captureBiometrics(Request $request, int $id, CardIssuance $issuance): ApplicationResource
    {
        $data = $request->validate([
            'photo' => ['required', 'string', 'max:8000000'],
            'signature' => ['required', 'string', 'max:2000000'],
            'fingerprint_template' => ['nullable', 'string', 'max:200000'],
            'issued_at' => ['nullable', 'string', 'max:150'],
        ]);

        $application = $issuance->captureBiometrics(Application::with('enrollmentCenter')->findOrFail($id), $this->officer($request), $data);

        return new ApplicationResource($application->load(['enrollmentCenter', 'card', 'documents', 'statusHistory']));
    }

    public function collect(Request $request, int $id, CardIssuance $issuance): ApplicationResource
    {
        $application = $issuance->markCollected(Application::findOrFail($id), $this->officer($request));

        return new ApplicationResource($application->load(['enrollmentCenter', 'card', 'statusHistory']));
    }

    public function document(int $id, int $documentId, DocumentStorage $storage): JsonResponse
    {
        $document = Application::findOrFail($id)->allDocuments()->findOrFail($documentId);

        return response()->json(['url' => $storage->temporaryUrl($document), 'expires_in' => config('nis.document_url_ttl_minutes') * 60]);
    }

    /**
     * Accepts raw numbers or the QR payload printed on slips
     * ("NIS-RCIS|APP:RC-2026-100001|REF:...").
     */
    private function normaliseSearch(string $raw): string
    {
        if (preg_match('/APP:([A-Z0-9\-]+)/i', $raw, $m)) {
            return strtoupper($m[1]);
        }

        return strtoupper(trim($raw));
    }

    private function officer(Request $request): User
    {
        return $request->user();
    }
}

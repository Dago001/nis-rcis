<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Enums\StaffRole;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\IntegrationCheck;
use App\Models\User;
use App\Notifications\ApplicationAssigned;
use App\Services\ApplicationSubmission;
use App\Services\ApplicationWorkflow;
use App\Services\CardIssuance;
use App\Services\DocumentStorage;
use App\Services\IntegrationChecks;
use App\Services\RiskChecker;
use App\Services\TemporaryResidenceCardService;
use App\Support\ApplicationRules;
use App\Support\Audit;
use App\Support\Like;
use App\Support\WorkingDays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Approval queue, assisted applications, biometrics desk and collection
 * (legacy pending-approvals.php, new-card.php, biometrics-capture.php).
 */
class ApplicationController
{
    public const FINGERS = ['R_THUMB', 'R_INDEX', 'R_MIDDLE', 'R_RING', 'R_LITTLE', 'L_THUMB', 'L_INDEX', 'L_MIDDLE', 'L_RING', 'L_LITTLE'];

    /** Longest base64 accepted per fingerprint format: templates are small, images larger (PNG is lossless). */
    private const FINGERPRINT_MAX = ['ISO_19794_2' => 20000, 'ANSI_378' => 20000, 'SIMULATED' => 20000, 'WSQ' => 200000, 'PNG' => 400000];

    /** First bytes of each fingerprint image format: WSQ's SOI marker, PNG's signature. */
    private const FINGERPRINT_IMAGE_START = ['WSQ' => "\xFF\xA0", 'PNG' => "\x89PNG"];

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'appointment_date' => ['nullable', 'date'],
            'enrollment_center_id' => ['nullable', 'integer'],
            'assigned' => ['nullable', Rule::in(['me', 'unassigned'])],
            'overdue' => ['nullable', 'boolean'],
            'queue' => ['nullable', 'string', 'max:50'],
        ]);

        $query = Application::query()
            ->with(['enrollmentCenter', 'assignee:id,fullname,service_number', 'vettedBy:id,fullname,service_number'])
            ->latest('id');

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

        $queue = $data['queue'] ?? null;
        $status = ! empty($data['status']) ? ($data['status'] instanceof ApplicationStatus ? $data['status']->value : (string) $data['status']) : null;

        if ($queue === 'vetting') {
            if ($status === ApplicationStatus::PendingApproval->value) {
                $query->where('status', ApplicationStatus::PendingApproval)->whereNull('vetted_at');
            } elseif ($status === ApplicationStatus::Queried->value) {
                $query->where('status', ApplicationStatus::Queried)->whereNull('vetted_at');
            } elseif ($status === ApplicationStatus::Vetted->value) {
                // Vetted applications eligible for biometric enrollment
                $query->where('status', ApplicationStatus::Vetted);
            } elseif ($status === ApplicationStatus::Rejected->value) {
                $query->where('status', ApplicationStatus::Rejected)->whereNull('vetted_at');
            } else {
                // All: union of all authorized records covered by these statuses without duplicates
                $query->where(function ($q) {
                    $q->whereIn('status', [
                        ApplicationStatus::PendingApproval,
                        ApplicationStatus::Vetted,
                        ApplicationStatus::Queried,
                        ApplicationStatus::Rejected,
                        ApplicationStatus::ApprovedForBiometrics,
                        ApplicationStatus::BiometricsCaptured,
                        ApplicationStatus::Approved,
                        ApplicationStatus::ReadyForCollection,
                        ApplicationStatus::Issued,
                    ])->orWhereNotNull('vetted_at');
                });
            }
        } elseif ($queue === 'approvals') {
            if ($status === ApplicationStatus::BiometricsCaptured->value || $status === 'AWAITING_APPROVAL' || $status === 'BIOMETRICS_COMPLETED' || $status === 'VETTED') {
                // Awaiting Final Approval: strictly requires completed biometric enrollment
                $query->where(function ($q) {
                    $q->where('status', ApplicationStatus::BiometricsCaptured)
                      ->orWhere(function ($sub) {
                          $sub->whereNotNull('biometrics_captured_at')
                              ->whereNull('decided_at')
                              ->whereNotIn('status', [ApplicationStatus::Queried, ApplicationStatus::Rejected]);
                      });
                });
            } elseif ($status === ApplicationStatus::Approved->value || $status === ApplicationStatus::ApprovedForBiometrics->value || $status === 'APPROVED') {
                // Approved records (including subsequent historical processing)
                $query->where(function ($q) {
                    $q->whereIn('status', [
                        ApplicationStatus::Approved,
                        ApplicationStatus::ApprovedForBiometrics,
                        ApplicationStatus::ReadyForCollection,
                        ApplicationStatus::Issued,
                    ])
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('decided_at')
                            ->whereNotIn('status', [ApplicationStatus::Queried, ApplicationStatus::Rejected]);
                    });
                });
            } elseif ($status === ApplicationStatus::Queried->value) {
                $query->where('status', ApplicationStatus::Queried)->whereNotNull('biometrics_captured_at');
            } elseif ($status === ApplicationStatus::Rejected->value) {
                $query->where('status', ApplicationStatus::Rejected)->whereNotNull('decided_at');
            } else {
                // All: all applications that have entered or passed the Approval workflow
                $query->where(function ($q) {
                    $q->whereIn('status', [
                        ApplicationStatus::BiometricsCaptured,
                        ApplicationStatus::Approved,
                        ApplicationStatus::ApprovedForBiometrics,
                        ApplicationStatus::ReadyForCollection,
                        ApplicationStatus::Issued,
                    ])
                    ->orWhereNotNull('biometrics_captured_at')
                    ->orWhere(function ($sub) {
                        $sub->whereIn('status', [ApplicationStatus::Queried, ApplicationStatus::Rejected])
                            ->whereNotNull('decided_at');
                    });
                });
            }
        } elseif (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($data['appointment_date'])) {
            $query->whereDate('appointment_date', $data['appointment_date']);
        }
        if (! empty($data['enrollment_center_id'])) {
            $query->where('enrollment_center_id', $data['enrollment_center_id']);
        }

        if (! empty($data['search'])) {
            $term = $this->normaliseSearch($data['search']);
            $driver = DB::connection()->getDriverName();
            $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';
            $pattern = Like::contains($term);
            $cleanNum = preg_replace('/[^A-Z0-9]/', '', $term);
            $concatForenamesSurname = $driver === 'sqlite' ? "(forenames || ' ' || surname)" : "concat(forenames, ' ', surname)";
            $concatSurnameForenames = $driver === 'sqlite' ? "(surname || ' ' || forenames)" : "concat(surname, ' ', forenames)";

            $query->where(function ($q) use ($term, $likeOp, $pattern, $cleanNum, $concatForenamesSurname, $concatSurnameForenames) {
                $q->where('application_number', $likeOp, $pattern)
                    ->orWhere('reference_number', $likeOp, $pattern)
                    ->orWhere('passport_number', $likeOp, $pattern)
                    ->orWhere('surname', $likeOp, $pattern)
                    ->orWhere('forenames', $likeOp, $pattern);

                if (! empty($cleanNum)) {
                    $q->orWhereRaw("replace(application_number, '-', '') " . $likeOp . " ?", ['%' . $cleanNum . '%'])
                      ->orWhereRaw("replace(reference_number, '-', '') " . $likeOp . " ?", ['%' . $cleanNum . '%']);
                }

                $q->orWhereRaw("{$concatForenamesSurname} {$likeOp} ?", [$pattern])
                  ->orWhereRaw("{$concatSurnameForenames} {$likeOp} ?", [$pattern]);
            });
        }

        $currentUser = $request->user();

        // Role-based visibility:
        // Enrollment Officer: Only applicants whose appointments fall on that particular day
        if ($currentUser && $currentUser->role === StaffRole::EnrollmentOfficer) {
            $query->whereDate('appointment_date', today())
                  ->where('status', ApplicationStatus::ApprovedForBiometrics);
        }

        // Data Entry Officer: Assisted applications assisted/created by them
        if ($currentUser && $currentUser->role === StaffRole::DataEntryOfficer) {
            $query->where('created_by', $currentUser->id);
        }

        $perPage = (int) ($data['per_page'] ?? 10);

        return ApplicationResource::collection($query->paginate($perPage));
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
        $application = Application::with([
            'enrollmentCenter', 'card', 'renewalOfCard', 'principal', 'dependants',
            'documents', 'statusHistory', 'payments', 'assignee:id,fullname,service_number',
            'vettedBy:id,fullname,service_number', 'decidedBy:id,fullname,service_number',
            'biometricsCapturedBy:id,fullname,service_number',
            'documentQueries.queriedBy:id,fullname,service_number',
            'documentQueries.resolvedBy:id,fullname,service_number',
        ])->findOrFail($id);

        return response()->json([
            'data' => new ApplicationResource($application),
            'photo_url' => $storage->temporaryUrlForPath($application->photo_path),
            'integration_checks' => $this->presentChecks($application),
            'payments' => $application->payments->map->only(['reference', 'status', 'amount_kobo', 'channel', 'paid_at', 'verified_at']),
        ]);
    }

    /**
     * Issue a document-specific query for an uploaded supporting document.
     */
    public function queryDocument(Request $request, int $id, int $documentId, ApplicationWorkflow $workflow): JsonResponse
    {
        $officer = $this->officer($request);
        abort_unless(
            $officer->hasRole(StaffRole::VettingOfficer, StaffRole::ApprovingOfficer, StaffRole::SuperAdmin, StaffRole::Admin),
            403,
            'You do not have permission to query supporting documents.'
        );

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'stage' => ['nullable', 'string', 'in:VETTING,APPROVAL'],
        ], [
            'reason.required' => 'Please enter the specific reason this supporting document requires correction.',
            'reason.min' => 'The query reason must be at least 3 characters.',
        ]);

        $application = Application::findOrFail($id);
        $document = ApplicationDocument::where('application_id', $application->id)->findOrFail($documentId);

        // Determine query stage
        $stage = ! empty($data['stage'])
            ? strtoupper($data['stage'])
            : ($application->status === ApplicationStatus::PendingApproval ? 'VETTING' : 'APPROVAL');

        // Create document query record
        $docQuery = \App\Models\ApplicationDocumentQuery::create([
            'application_id' => $application->id,
            'document_id' => $document->id,
            'document_type' => $document->type->value,
            'stage' => $stage,
            'query_reason' => $data['reason'],
            'status' => \App\Models\ApplicationDocumentQuery::STATUS_QUERY_RAISED,
            'queried_by' => $officer->id,
            'queried_at' => now(),
        ]);

        $queryNote = "Document queried ({$document->type->label()}): {$data['reason']}";

        // Transition application to QUERIED if not already QUERIED
        if ($application->status !== ApplicationStatus::Queried) {
            $application = $workflow->transition($application, ApplicationStatus::Queried, $officer, $queryNote, function (Application $a) use ($queryNote) {
                $a->decided_by = null;
                $a->decided_at = null;
                $a->decision_notes = $queryNote;
            });
        } else {
            $application->decision_notes = $queryNote;
            $application->saveQuietly();

            \App\Models\ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status' => ApplicationStatus::Queried->value,
                'to_status' => ApplicationStatus::Queried->value,
                'actor_type' => get_class($officer),
                'actor_id' => $officer->id,
                'notes' => $queryNote,
                'created_at' => now(),
            ]);
        }

        Audit::log('DOCUMENT_QUERIED', "Document {$document->type->label()} queried on {$application->application_number} ({$stage} stage) by {$officer->fullname}: {$data['reason']}", $application);

        // Dispatch notification to applicant
        try {
            $application->applicant?->notify(new \App\Notifications\DocumentQueriedNotification($application, $docQuery));
        } catch (\Throwable $e) {
            Log::warning("Failed to send DocumentQueriedNotification: " . $e->getMessage());
        }

        return response()->json([
            'message' => "Query for '{$document->type->label()}' has been submitted successfully.",
            'query' => [
                'id' => $docQuery->id,
                'document_id' => $docQuery->document_id,
                'query_reason' => $docQuery->query_reason,
                'stage' => $docQuery->stage,
                'status' => $docQuery->status,
                'status_label' => $docQuery->status_label,
                'queried_at' => $docQuery->queried_at?->toIso8601String(),
                'queried_by' => [
                    'fullname' => $officer->fullname,
                    'service_number' => $officer->service_number,
                ],
            ],
            'application' => new ApplicationResource($application->fresh([
                'enrollmentCenter', 'card', 'renewalOfCard', 'principal', 'dependants',
                'documents', 'statusHistory', 'payments', 'assignee:id,fullname,service_number',
                'vettedBy:id,fullname,service_number', 'decidedBy:id,fullname,service_number',
                'biometricsCapturedBy:id,fullname,service_number',
                'documentQueries.queriedBy:id,fullname,service_number',
            ])),
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
            ...ApplicationRules::quota(),
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
     * VET | APPROVE (for biometrics) | QUERY | REJECT.
     */
    public function decide(Request $request, int $id, ApplicationWorkflow $workflow): ApplicationResource
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['VET', 'APPROVE', 'QUERY', 'REJECT'])],
            'notes' => ['required_if:decision,QUERY,REJECT', 'nullable', 'string', 'max:2000'],
        ]);

        $officer = $this->officer($request);
        $application = Application::findOrFail($id);

        if ($data['decision'] === 'VET') {
            abort_unless(
                $officer->hasRole(StaffRole::VettingOfficer, StaffRole::SuperAdmin, StaffRole::Admin),
                403,
                'Only a Vetting Officer or Administrator can vet applications.'
            );
            $unresolved = $application->unresolvedDocumentQueries()->count();
            abort_if(
                $unresolved > 0,
                422,
                "Cannot proceed with vetting: application {$application->application_number} has {$unresolved} unresolved document query/queries. All queried documents must be corrected and resolved before vetting can be completed."
            );
            abort_unless(
                $application->status === ApplicationStatus::PendingApproval,
                422,
                "Application {$application->application_number} is {$application->status->value}; only applications awaiting vetting can be vetted."
            );

            $notes = ! empty($data['notes']) ? $data['notes'] : 'Application particulars and supporting documents examined and verified by Vetting Officer.';

            $application = $workflow->transition($application, ApplicationStatus::Vetted, $officer, $notes, function (Application $a) use ($officer, $notes) {
                $a->vetted_by = $officer->id;
                $a->vetted_at = now();
                $a->vetting_notes = $notes;
            });

            // Mark active vetting document queries as resolved
            $application->documentQueries()
                ->where('stage', 'VETTING')
                ->where('status', '!=', \App\Models\ApplicationDocumentQuery::STATUS_RESOLVED)
                ->update([
                    'status' => \App\Models\ApplicationDocumentQuery::STATUS_RESOLVED,
                    'resolved_by' => $officer->id,
                    'resolved_at' => now(),
                    'resolution_notes' => 'Document verified and resolved upon vetting approval.',
                ]);

            return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory', 'vettedBy', 'documentQueries.queriedBy']));
        }

        if ($data['decision'] === 'APPROVE') {
            abort_unless(
                $officer->hasRole(StaffRole::ApprovingOfficer, StaffRole::SuperAdmin, StaffRole::Admin),
                403,
                'Only an Approving Officer or Administrator can grant final approval.'
            );
            $unresolved = $application->unresolvedDocumentQueries()->count();
            abort_if(
                $unresolved > 0,
                422,
                "Cannot proceed with final approval: application {$application->application_number} has {$unresolved} unresolved document query/queries. All queried documents must be corrected and resolved before approval can be granted."
            );
            abort_if(
                $application->status === ApplicationStatus::PendingApproval,
                422,
                "Application {$application->application_number} has not been vetted. Vetting and biometric enrollment must be completed before final approval can be granted."
            );
            abort_if(
                $application->status === ApplicationStatus::Queried,
                422,
                "Application {$application->application_number} is currently queried and cannot be approved."
            );
            abort_if(
                $application->status === ApplicationStatus::Vetted && $application->biometrics_captured_at === null,
                422,
                "Application {$application->application_number} has not completed biometric enrollment. Biometrics must be enrolled and confirmed before final approval can be granted."
            );
            abort_unless(
                $application->biometrics_captured_at !== null || in_array($application->status, [ApplicationStatus::BiometricsCaptured, ApplicationStatus::ApprovedForBiometrics], true),
                422,
                "Application {$application->application_number} must have completed required biometrics to receive final approval."
            );

            $notes = ! empty($data['notes']) ? $data['notes'] : 'Final approval granted. Card queued for physical production.';

            $application = $workflow->transition($application, ApplicationStatus::Approved, $officer, $notes, function (Application $a) use ($officer, $notes) {
                $a->decided_by = $officer->id;
                $a->decided_at = now();
                $a->decision_notes = $notes;

                if ($a->card) {
                    $a->card->status = \App\Enums\CardStatus::Approved;
                    $a->card->save();
                }

                if ($a->has_cod && ! empty($a->cod_changes)) {
                    $changes = is_array($a->cod_changes) ? $a->cod_changes : json_decode($a->cod_changes, true);
                    foreach ($changes as $key => $change) {
                        $field = $change['field'] ?? (is_string($key) ? $key : null);
                        $newVal = $change['requested_value'] ?? $change['requested'] ?? null;
                        if ($field === 'name') {
                            if (! empty($change['requested_surname'])) {
                                $a->surname = $change['requested_surname'];
                            }
                            if (! empty($change['requested_forenames'])) {
                                $a->forenames = $change['requested_forenames'];
                            }
                        } elseif ($newVal !== null && in_array($field, Application::PARTICULARS, true)) {
                            $a->{$field} = $newVal;
                        }
                    }
                    $a->cod_status = 'APPROVED';
                }
            });

            // Mark active approval document queries as resolved
            $application->documentQueries()
                ->where('stage', 'APPROVAL')
                ->where('status', '!=', \App\Models\ApplicationDocumentQuery::STATUS_RESOLVED)
                ->update([
                    'status' => \App\Models\ApplicationDocumentQuery::STATUS_RESOLVED,
                    'resolved_by' => $officer->id,
                    'resolved_at' => now(),
                    'resolution_notes' => 'Document verified and resolved upon final approval.',
                ]);

            return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory', 'vettedBy', 'decidedBy', 'biometricsCapturedBy', 'card', 'documentQueries.queriedBy']));
        }

        if ($data['decision'] === 'QUERY') {
            abort_unless(
                $officer->hasRole(StaffRole::VettingOfficer, StaffRole::ApprovingOfficer, StaffRole::SuperAdmin, StaffRole::Admin),
                403,
                'You do not have permission to query this application.'
            );
            $notes = $data['notes'];

            $application = $workflow->transition($application, ApplicationStatus::Queried, $officer, $notes, function (Application $a) use ($notes) {
                $a->decided_by = null;
                $a->decided_at = null;
                $a->decision_notes = $notes;
            });

            return new ApplicationResource($application->load(['enrollmentCenter', 'documents', 'statusHistory', 'vettedBy', 'biometricsCapturedBy']));
        }

        if ($data['decision'] === 'REJECT') {
            abort(422, 'The Reject action has been removed from Vetting and Approval workflows. Please use Query if additional documentation or clarification is needed.');
        }

        abort(422, 'Invalid decision.');
    }

    public function captureBiometrics(Request $request, int $id, CardIssuance $issuance): ApplicationResource
    {
        $isSimulation = $request->boolean('simulation');

        $rules = [
            'photo' => ['nullable', 'string', 'max:8000000'],
            'signature' => ['nullable', 'string', 'max:2000000'],
            'simulation' => ['nullable', 'boolean'],
            'issued_at' => ['nullable', 'string', 'max:150'],
            'fingerprint_template' => ['nullable', 'string', 'max:200000'],
            'fingerprints' => ['nullable', 'array', 'min:0', 'max:10'],
            'fingerprints.*.finger' => ['required', 'distinct', Rule::in(self::FINGERS)],
            'fingerprints.*.template' => ['required', 'string', 'max:'.max(self::FINGERPRINT_MAX), 'regex:/^[A-Za-z0-9+\/=]+$/'],
            'fingerprints.*.format' => ['required', Rule::in(['ISO_19794_2', 'ANSI_378', 'WSQ', 'PNG', 'SIMULATED'])],
            'fingerprints.*.quality' => ['nullable', 'integer', 'between:0,100'],
            'fingerprints.*.nfiq' => ['nullable', 'integer', 'between:1,5'],
            'fingerprints.*.device' => ['nullable', 'string', 'max:100'],
        ];

        $data = $request->validate($rules);
        $data['simulation'] = $isSimulation;

        $target = Application::with('enrollmentCenter')->findOrFail($id);
        abort_unless(
            in_array($target->status, [ApplicationStatus::Vetted, ApplicationStatus::ApprovedForBiometrics], true),
            403,
            "Application {$target->application_number} must be in Vetted status to proceed with biometric enrollment."
        );
        abort_unless(
            $target->payment_status === 'PAID' || $target->payments()->where('status', 'SUCCESS')->exists(),
            422,
            "Application {$target->application_number} requires verified payment before biometrics capture."
        );

        $application = $issuance->captureBiometrics($target, $this->officer($request), $data);

        return new ApplicationResource($application->load(['enrollmentCenter', 'card', 'documents', 'statusHistory', 'vettedBy', 'decidedBy', 'biometricsCapturedBy']));
    }

    /** Latest answers from Interpol SLTD and the Ministry of Interior quota register. */
    public function integrationChecks(int $id): JsonResponse
    {
        return response()->json(['data' => $this->presentChecks(Application::findOrFail($id))]);
    }

    /** Run the external checks again (for example when a connection was down). */
    public function runIntegrationChecks(Request $request, int $id, IntegrationChecks $checks, RiskChecker $risk): JsonResponse
    {
        $application = Application::findOrFail($id);
        $checks->run($application, $this->officer($request));
        $risk->check($application);

        return response()->json(['data' => $this->presentChecks($application), 'risk_flags' => $application->risk_flags ?? []]);
    }

    private function presentChecks(Application $application): array
    {
        return IntegrationChecks::latest($application)->map(fn (IntegrationCheck $c) => [
            'service' => $c->service, 'status' => $c->status, 'summary' => $c->summary, 'reference' => $c->reference,
            'details' => $c->details, 'checked_at' => $c->created_at?->toIso8601String(), 'checked_by' => $c->checker?->fullname ?? 'Automatic',
        ])->all();
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

    public function slip(Request $request, int $id, DocumentStorage $storage): JsonResponse
    {
        $application = Application::with(['enrollmentCenter', 'documents', 'principal', 'card'])->findOrFail($id);
        $payment = $application->payments()->where('status', 'SUCCESS')->latest('id')->first();

        return response()->json([
            'application' => new ApplicationResource($application),
            'payment' => $payment ? ['reference' => $payment->reference, 'amount_naira' => $payment->amount_kobo / 100, ...\App\Services\PaystackGateway::receipt($payment)] : null,
            'documents' => $application->documents
                ->reject(fn ($d) => $d->type === \App\Enums\DocumentType::Signature)
                ->map(fn ($d) => ['type' => $d->type->value, 'label' => $d->type->label()])->values(),
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'photo_url' => $storage->temporaryUrlForPath($application->photo_path ?? $application->documents->firstWhere('type', \App\Enums\DocumentType::Photo)?->path),
            'signature_url' => $storage->temporaryUrlForPath($application->signature_path ?? $application->documents->firstWhere('type', \App\Enums\DocumentType::Signature)?->path),
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

    public function temporaryCard(Request $request, int $id, DocumentStorage $storage, TemporaryResidenceCardService $service): JsonResponse
    {
        $application = Application::findOrFail($id);
        $service->assertEligible($application);

        return response()->json([
            'data' => $service->getData($application, $storage),
        ]);
    }

    public function temporaryCardPdf(Request $request, int $id, DocumentStorage $storage, TemporaryResidenceCardService $service): Response
    {
        $application = Application::findOrFail($id);
        $service->assertEligible($application);
        $pdfBytes = $service->renderPdf($application, $storage);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="NIS-temporary-card-'.$application->application_number.'.pdf"',
            'Cache-Control' => 'no-cache, private',
        ]);
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

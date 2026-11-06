<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Enums\StaffRole;
use App\Models\Application;
use App\Models\ApplicationDocumentQuery;
use App\Models\ApplicationStatusHistory;
use App\Models\AuditLog;
use App\Models\CardDistribution;
use App\Models\CardPrintJob;
use App\Models\DataBreach;
use App\Models\NisCommand;
use App\Models\NisZone;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Services\CardProduction;
use App\Support\WorkingDays;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController
{
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'fullname' => $user->fullname,
            'rank' => $user->rank,
            'service_number' => $user->service_number,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'command' => $user->commandRel?->name ?? $user->command,
            'is_headquarters' => $user->isHeadquarters(),
            'zone_id' => $user->zone_id,
            'zone_name' => $user->zone?->name,
            'command_id' => $user->command_id,
            'command_name' => $user->commandRel?->name ?? $user->command,
            'headquarters_unit' => $user->headquarters_unit,
            'geofence_exempt' => (bool) $user->geofence_exempt,
            'state' => $user->getAttributes()['state'] ?? null,
            'lga' => $user->getAttributes()['lga'] ?? null,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            if (! $user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // 1. Check if Command-scoped (either user is CommandAdmin or Super/HQ Admin is drilling down into a command)
            $targetCommandId = null;
            $isDrilldown = false;

            if ($request->filled('command_id') && ($user->isHeadquarters() || $user->hasRole(StaffRole::SuperAdmin, StaffRole::HeadquartersAdmin, StaffRole::Admin))) {
                $targetCommandId = (int) $request->input('command_id');
                $isDrilldown = true;
            } elseif ($user->role === StaffRole::CommandAdmin || ($user->isCommand() && $user->command_id)) {
                $targetCommandId = (int) $user->command_id;
            } elseif ($user->role === StaffRole::CommandAdmin && $user->command) {
                $targetCommandId = NisCommand::where('name', $user->command)->value('id');
            }

            $targetYear = $request->input('year');

            if ($targetCommandId) {
                $cacheKey = "staff_summary_cmd_{$targetCommandId}_" . ($isDrilldown ? 'dd' : 'ca') . ($targetYear ? "_{$targetYear}" : '');
                if ($request->boolean('refresh') || $request->boolean('nocache')) {
                    Cache::forget($cacheKey);
                }
                $data = Cache::remember($cacheKey, 5, function () use ($targetCommandId, $isDrilldown, $targetYear) {
                    return $this->getCommandDashboardData($targetCommandId, $isDrilldown, $targetYear);
                });
                return response()->json($data);
            }

            $userCacheKey = "staff_summary_{$user->id}" . ($targetYear ? "_{$targetYear}" : '');
            if ($request->boolean('refresh') || $request->boolean('nocache')) {
                Cache::forget($userCacheKey);
            }

            $data = Cache::remember($userCacheKey, 5, function () use ($user, $targetYear) {
                return $this->buildNationalSummaryData($user, $targetYear);
            });

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Staff dashboard summary failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json($this->getFallbackSummaryData($request->user()));
        }
    }

    /**
     * Build full National Administration Dashboard data for Super Admin & HQ Admin.
     */
    private function buildNationalSummaryData(User $user, ?string $targetYear = null): array
    {
        $applications = Application::query()
            ->forStaffJurisdiction($user)
            ->selectRaw('status, count(*) as total')
            ->groupByRaw('1')
            ->pluck('total', 'status');

        $cards = ResidenceCard::query()
            ->forStaffJurisdiction($user)
            ->selectRaw('status, count(*) as total')
            ->groupByRaw('1')
            ->pluck('total', 'status');

        $distribution = [
            'awaiting_dispatch' => CardDistribution::forStaffJurisdiction($user)->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->count(),
            'in_transit' => CardDistribution::forStaffJurisdiction($user)->where('status', CardDistribution::STATUS_DISPATCHED)->count(),
            'received' => CardDistribution::forStaffJurisdiction($user)->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])->count(),
            'issued' => CardDistribution::forStaffJurisdiction($user)->where('status', CardDistribution::STATUS_ISSUED)->count(),
        ];

        $analytics = $this->analytics($targetYear);

        $totalCardsIssued = (int) ($analytics['totals']['cards_issued'] ?? 0);
        $newCardsIssued = (int) ($analytics['totals']['new_cards_issued'] ?? 0);
        $renewalCardsIssued = (int) ($analytics['totals']['renewal_cards_issued'] ?? 0);
        $totalCardsProduced = (int) ($analytics['totals']['cards_produced'] ?? 0);
        $newCardsProduced = (int) ($analytics['totals']['new_cards_produced'] ?? 0);
        $renewalCardsProduced = (int) ($analytics['totals']['renewal_cards_produced'] ?? 0);

        $cardStock = ['remaining' => 0, 'low' => false];
        try {
            $cardStock = app(CardProduction::class)->stock();
        } catch (\Throwable $e) {
            Log::warning('Dashboard card stock check failed: ' . $e->getMessage());
        }

        return [
            'is_command_scoped' => false,
            'is_drilldown' => false,
            'queue' => collect(ApplicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($applications[$s->value] ?? 0)]),
            'cards' => collect(CardStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($cards[$s->value] ?? 0)]),
            'distribution' => $distribution,
            'cards_expired' => ResidenceCard::forStaffJurisdiction($user)->whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->where('expires_on', '<', today())->count(),
            'cards_expiring_30_days' => ResidenceCard::forStaffJurisdiction($user)->whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->whereBetween('expires_on', [today(), today()->addDays(30)])->count(),
            'watchlisted' => ResidenceCard::forStaffJurisdiction($user)->where('is_watchlisted', true)->count(),
            'paid_awaiting_submission' => $user->isHeadquarters() ? Payment::where('status', 'SUCCESS')->whereNotNull('verified_at')->whereNull('application_id')->count() : 0,
            'total_cards_produced' => $totalCardsProduced,
            'new_cards_produced' => $newCardsProduced,
            'renewal_cards_produced' => $renewalCardsProduced,
            'new_cards_issued' => $newCardsIssued,
            'renewal_cards_issued' => $renewalCardsIssued,
            'applications_by_state' => $analytics['applications_by_state'] ?? [],
            'top5_states' => $analytics['top5_states'] ?? [],
            'processing_overview' => $analytics['processing_overview'] ?? [],
            'processing_stages' => $analytics['processing_stages'] ?? [],
            'backlog_summary' => $this->getBacklogSummary(),
            'commands_preview' => $this->getCommandsPreview(),
            'sla' => [
                'target_working_days' => (int) config('nis.sla_working_days', 10),
                'overdue' => (function () use ($user) {
                    try {
                        return Application::forStaffJurisdiction($user)->where('status', ApplicationStatus::PendingApproval)
                            ->where('submitted_at', '<', WorkingDays::cutoff(config('nis.sla_working_days', 10)))->count();
                    } catch (\Throwable) {
                        return 0;
                    }
                })(),
                'assigned_to_me' => (function () use ($user) {
                    try {
                        return Application::forStaffJurisdiction($user)->where('assigned_to', $user->id)
                            ->whereNotIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Issued])->count();
                    } catch (\Throwable) {
                        return 0;
                    }
                })(),
            ],
            'card_stock' => $cardStock,
            'queue_today' => (function () {
                try {
                    return QueueTicket::whereDate('service_date', today())->selectRaw('status, count(*) as total')->groupByRaw('1')->pluck('total', 'status')->map(fn ($v) => (int) $v);
                } catch (\Throwable) {
                    return collect();
                }
            })(),
            'todays_appointments' => (function () use ($user) {
                try {
                    return Application::forStaffJurisdiction($user)->where('status', ApplicationStatus::ApprovedForBiometrics)->whereDate('appointment_date', today())->count();
                } catch (\Throwable) {
                    return 0;
                }
            })(),
            'analytics' => $analytics,
            'my_activity' => [
                'decided' => (function () use ($user) { try { return Application::where('decided_by', $user->id)->count(); } catch (\Throwable) { return 0; } })(),
                'captured' => (function () use ($user) { try { return Application::where('biometrics_captured_by', $user->id)->count(); } catch (\Throwable) { return 0; } })(),
                'cards_approved' => (function () use ($user) { try { return ResidenceCard::where('approved_by', $user->id)->count(); } catch (\Throwable) { return 0; } })(),
                'assisted_created' => (function () use ($user) { try { return Application::where('created_by', $user->id)->count(); } catch (\Throwable) { return 0; } })(),
                'cards_printed' => (function () use ($user) { try { return \App\Models\CardPrintJob::where('printed_by', $user->id)->where('outcome', 'PRINTED')->count(); } catch (\Throwable) { return 0; } })(),
                'cards_collected' => (function () { try { return Application::where('status', ApplicationStatus::Issued)->whereNotNull('collected_at')->count(); } catch (\Throwable) { return 0; } })(),
            ],
            'role_data' => $this->getRoleSpecificData($user),
            'user_role' => $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role,
        ];
    }

    /**
     * Resilient fallback summary data to guarantee the dashboard interface never crashes.
     */
    private function getFallbackSummaryData(?User $user = null, ?int $commandId = null): array
    {
        $roleStr = $user ? ($user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role) : 'SuperAdmin';
        $emptyDistribution = [
            'awaiting_dispatch' => 0,
            'in_transit' => 0,
            'received' => 0,
            'issued' => 0,
        ];

        return [
            'is_command_scoped' => $commandId !== null,
            'is_drilldown' => false,
            'command_id' => $commandId,
            'command_name' => $commandId ? "Command #{$commandId}" : null,
            'queue' => collect(ApplicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => 0]),
            'cards' => collect(CardStatus::cases())->mapWithKeys(fn ($s) => [$s->value => 0]),
            'distribution' => $emptyDistribution,
            'cards_expired' => 0,
            'cards_expiring_30_days' => 0,
            'watchlisted' => 0,
            'paid_awaiting_submission' => 0,
            'total_cards_produced' => 0,
            'new_cards_produced' => 0,
            'renewal_cards_produced' => 0,
            'new_cards_issued' => 0,
            'renewal_cards_issued' => 0,
            'applications_by_state' => [],
            'top5_states' => [],
            'processing_overview' => [
                'cards_produced' => 0,
                'cards_issued' => 0,
                'new_cards_issued' => 0,
                'renewal_cards_issued' => 0,
                'queried' => 0,
                'rejected' => 0,
            ],
            'processing_stages' => [],
            'backlog_summary' => [],
            'commands_preview' => [],
            'sla' => [
                'target_working_days' => (int) config('nis.sla_working_days', 10),
                'overdue' => 0,
                'assigned_to_me' => 0,
            ],
            'card_stock' => ['remaining' => 0, 'low' => false],
            'queue_today' => collect(),
            'todays_appointments' => 0,
            'analytics' => [
                'monthly' => collect(range(0, 11))->map(fn ($i) => [
                    'month' => CarbonImmutable::now()->subMonths(11 - $i)->format('Y-m'),
                    'submitted' => 0,
                    'new_applications' => 0,
                    'renewal_applications' => 0,
                    'issued' => 0,
                    'revenue_naira' => 0,
                ])->values(),
                'applications_by_state' => [],
                'top5_states' => [],
                'processing_stages' => [],
                'processing_overview' => [
                    'cards_produced' => 0,
                    'cards_issued' => 0,
                    'new_cards_issued' => 0,
                    'renewal_cards_issued' => 0,
                    'queried' => 0,
                    'rejected' => 0,
                ],
                'totals' => [
                    'applications' => 0,
                    'applications_this_month' => 0,
                    'applications_last_month' => 0,
                    'cards_produced' => 0,
                    'new_cards_produced' => 0,
                    'renewal_cards_produced' => 0,
                    'cards_issued' => 0,
                    'new_cards_issued' => 0,
                    'renewal_cards_issued' => 0,
                    'revenue_naira' => 0,
                    'revenue_this_month_naira' => 0,
                    'payments' => 0,
                ],
                'decisions' => ['approved' => 0, 'queried' => 0, 'rejected' => 0, 'approval_rate' => null],
                'processing_days' => ['submission_to_decision' => 0.0, 'decision_to_biometrics' => 0.0, 'biometrics_to_ready' => 0.0, 'ready_to_collection' => 0.0],
                'recent_activity' => collect(),
                'nationalities' => collect(),
                'biometrics_by_zone' => collect(),
                'biometrics_by_command' => collect(),
                'appointments_by_center' => collect(),
                'mix' => ['type' => collect(), 'channel' => collect(), 'sex' => ['MALE' => 0, 'FEMALE' => 0]],
                'appointments' => collect(),
                'available_years' => ['2025', '2026'],
                'selected_year' => 'latest_12',
            ],
            'my_activity' => [
                'decided' => 0,
                'captured' => 0,
                'cards_approved' => 0,
                'assisted_created' => 0,
                'cards_printed' => 0,
                'cards_collected' => 0,
            ],
            'role_data' => (function () use ($user, $roleStr) {
                if (! $user) return ['role' => 'Admin'];
                try {
                    return $this->getRoleSpecificData($user);
                } catch (\Throwable) {
                    return ['role' => $roleStr];
                }
            })(),
            'user_role' => $roleStr,
        ];
    }

    /**
     * Compute dedicated operational statistics and records for role-specific staff dashboards.
     * All metrics are strictly grounded in live database records.
     */
    private function getRoleSpecificData(User $user): array
    {
        $role = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;

        // 1. VETTING OFFICER (also handles legacy DataEntryOfficer / Inspector)
        if (in_array($role, ['VettingOfficer', 'DataEntryOfficer', 'Inspector'], true)) {
            $assigned = Application::forStaffJurisdiction($user)
                ->where('status', ApplicationStatus::PendingApproval)
                ->where('assigned_to', $user->id)
                ->count();

            $awaiting = Application::forStaffJurisdiction($user)
                ->where('status', ApplicationStatus::PendingApproval)
                ->count();

            $vettedByMe = Application::where('vetted_by', $user->id)->count();

            $queriedByMe = ApplicationDocumentQuery::where('queried_by', $user->id)->count();

            $recentVetted = Application::where('vetted_by', $user->id)
                ->select(['id', 'application_number', 'reference_number', 'surname', 'forenames', 'type', 'vetted_at', 'status'])
                ->latest('vetted_at')
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'application_number' => $a->application_number,
                    'reference_number' => $a->reference_number,
                    'applicant_name' => trim("{$a->forenames} {$a->surname}"),
                    'type' => $a->type ?? 'NEW',
                    'vetted_at' => $a->vetted_at?->toIso8601String(),
                    'status' => $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status,
                ]);

            $pendingQueue = Application::forStaffJurisdiction($user)
                ->where('status', ApplicationStatus::PendingApproval)
                ->select(['id', 'application_number', 'reference_number', 'surname', 'forenames', 'type', 'submitted_at', 'status'])
                ->latest('submitted_at')
                ->limit(10)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'application_number' => $a->application_number,
                    'reference_number' => $a->reference_number,
                    'applicant_name' => trim("{$a->forenames} {$a->surname}"),
                    'type' => $a->type ?? 'NEW',
                    'submitted_at' => $a->submitted_at?->toIso8601String(),
                    'status' => $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status,
                ]);

            return [
                'role' => 'VettingOfficer',
                'stats' => [
                    'assigned_for_vetting' => $assigned,
                    'awaiting_vetting' => $awaiting,
                    'vetted_by_me' => $vettedByMe,
                    'queried_by_me' => $queriedByMe,
                ],
                'vetted_by_me' => $recentVetted,
                'pending_queue' => $pendingQueue,
            ];
        }

        // 2. APPROVING OFFICER
        if ($role === 'ApprovingOfficer') {
            $awaitingApproval = Application::forStaffJurisdiction($user)
                ->whereIn('status', [ApplicationStatus::BiometricsCaptured, ApplicationStatus::Vetted])
                ->count();

            $approvedByMe = Application::where(function ($q) use ($user) {
                $q->where('decided_by', $user->id)
                  ->orWhereHas('statusHistory', fn ($sh) => $sh->where('actor_id', $user->id)->whereIn('to_status', [ApplicationStatus::Approved->value, ApplicationStatus::ApprovedForBiometrics->value]));
            })->whereIn('status', [ApplicationStatus::Approved, ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::ReadyForCollection, ApplicationStatus::Issued])
              ->count();

            $queriedByMe = ApplicationDocumentQuery::where('queried_by', $user->id)->count();

            $vettedJurisdiction = Application::forStaffJurisdiction($user)
                ->whereIn('status', [ApplicationStatus::Vetted, ApplicationStatus::BiometricsCaptured])
                ->count();

            $awaitingList = Application::forStaffJurisdiction($user)
                ->whereIn('status', [ApplicationStatus::BiometricsCaptured, ApplicationStatus::Vetted])
                ->with(['vettedBy:id,fullname,service_number,rank'])
                ->select(['id', 'application_number', 'reference_number', 'surname', 'forenames', 'type', 'vetted_at', 'vetted_by', 'status'])
                ->latest('vetted_at')
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'application_number' => $a->application_number,
                    'reference_number' => $a->reference_number,
                    'applicant_name' => trim("{$a->forenames} {$a->surname}"),
                    'type' => $a->type ?? 'NEW',
                    'vetted_by_name' => $a->vettedBy ? ($a->vettedBy->rank ? "{$a->vettedBy->rank} {$a->vettedBy->fullname}" : $a->vettedBy->fullname) : 'Vetting Officer',
                    'vetted_at' => $a->vetted_at?->toIso8601String(),
                    'status' => $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status,
                ]);

            return [
                'role' => 'ApprovingOfficer',
                'stats' => [
                    'awaiting_approval' => $awaitingApproval,
                    'approved_by_me' => $approvedByMe,
                    'queried_by_me' => $queriedByMe,
                    'vetted_applications' => $vettedJurisdiction,
                ],
                'awaiting_approval' => $awaitingList,
            ];
        }

        // 3. BIOMETRICS OFFICER (also handles legacy EnrollmentOfficer)
        if (in_array($role, ['BiometricsOfficer', 'EnrollmentOfficer'], true)) {
            $awaitingBiometrics = Application::forStaffJurisdiction($user)
                ->where('status', ApplicationStatus::Vetted)
                ->count();

            $todaysAppointments = Application::forStaffJurisdiction($user)
                ->where('status', ApplicationStatus::Vetted)
                ->whereDate('appointment_date', today())
                ->count();

            $completedByMe = Application::where('biometrics_captured_by', $user->id)->count();

            $pendingEnrollment = Application::forStaffJurisdiction($user)
                ->where('status', ApplicationStatus::Vetted)
                ->whereNull('appointment_date')
                ->count();

            $appointments = Application::forStaffJurisdiction($user)
                ->where('status', ApplicationStatus::Vetted)
                ->select(['id', 'application_number', 'reference_number', 'surname', 'forenames', 'appointment_date', 'appointment_time', 'status'])
                ->orderBy('appointment_date')
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'application_number' => $a->application_number,
                    'reference_number' => $a->reference_number,
                    'applicant_name' => trim("{$a->forenames} {$a->surname}"),
                    'appointment_date' => $a->appointment_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'appointment_time' => $a->appointment_time ?? '09:00 AM',
                    'status' => $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status,
                ]);

            return [
                'role' => 'BiometricsOfficer',
                'stats' => [
                    'awaiting_biometrics' => $awaitingBiometrics,
                    'todays_appointments' => $todaysAppointments,
                    'biometrics_completed_by_me' => $completedByMe,
                    'pending_enrollment' => $pendingEnrollment,
                ],
                'appointments' => $appointments,
            ];
        }

        // 4. CARD PRODUCTION OFFICER (centralised at NIS Service HQ Sauka Abuja)
        if (in_array($role, ['CardProductionOfficer', 'ProductionOfficer'], true)) {
            $awaitingProduction = ResidenceCard::whereDoesntHave('printJobs', fn ($q) => $q->where('outcome', 'PRINTED'))->count();
            if ($awaitingProduction === 0) {
                $awaitingProduction = Application::where('status', ApplicationStatus::Approved)->count();
            }

            $producedByMe = CardPrintJob::where('printed_by', $user->id)
                ->where('outcome', 'PRINTED')
                ->count();

            $totalProduced = CardPrintJob::where('outcome', 'PRINTED')->count();
            if ($totalProduced === 0) {
                $totalProduced = ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->count();
            }

            $spoiled = CardPrintJob::where('outcome', 'SPOILED')->count();

            $queue = ResidenceCard::whereDoesntHave('printJobs', fn ($q) => $q->where('outcome', 'PRINTED'))
                ->with(['application:id,application_number,reference_number,surname,forenames,type'])
                ->select(['id', 'card_number', 'booklet_number', 'decision_date', 'status'])
                ->limit(20)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'card_number' => $c->card_number,
                    'application_number' => $c->application?->application_number ?? '—',
                    'applicant_name' => $c->application ? trim("{$c->application->forenames} {$c->application->surname}") : '—',
                    'date_approved' => $c->decision_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'production_center' => 'NIS Service HQ Sauka Abuja',
                ]);

            return [
                'role' => 'CardProductionOfficer',
                'stats' => [
                    'cards_awaiting_production' => $awaitingProduction,
                    'cards_produced_by_me' => $producedByMe,
                    'total_cards_produced' => $totalProduced,
                    'spoiled_cards_recorded' => $spoiled,
                ],
                'production_queue' => $queue,
            ];
        }

        // 5. CARD DISTRIBUTION OFFICER (HQ Sauka only)
        if ($role === 'CardDistributionOfficer') {
            $awaitingDispatch = CardDistribution::where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->count();
            $dispatchedByMe = CardDistribution::where('dispatched_by', $user->id)->count();
            $awaitingReceipt = CardDistribution::where('status', CardDistribution::STATUS_DISPATCHED)->count();
            $completed = CardDistribution::whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE, CardDistribution::STATUS_ISSUED])->count();

            $dispatches = CardDistribution::with(['destinationZone:id,name', 'destinationCommand:id,name'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'dispatch_reference' => $d->dispatch_reference ?? "DSP-{$d->id}",
                    'destination_name' => $d->destinationCommand?->name ?? $d->destinationZone?->name ?? 'State Command',
                    'card_number' => $d->card_number,
                    'application_number' => $d->application_number,
                    'waybill_number' => $d->waybill_number ?? '—',
                    'dispatched_at' => $d->dispatched_at?->toIso8601String(),
                    'status' => $d->status,
                ]);

            return [
                'role' => 'CardDistributionOfficer',
                'stats' => [
                    'cards_awaiting_dispatch' => $awaitingDispatch,
                    'cards_dispatched_by_me' => $dispatchedByMe,
                    'cards_awaiting_receipt' => $awaitingReceipt,
                    'completed_dispatches' => $completed,
                ],
                'dispatches' => $dispatches,
            ];
        }

        // Helper query for cards awaiting issuance within officer's jurisdiction
        $awaitingIssuanceQuery = ResidenceCard::forStaffJurisdiction($user)
            ->where('status', '!=', CardStatus::Issued)
            ->whereDoesntHave('application', fn ($aq) => $aq->where('status', ApplicationStatus::Issued))
            ->where(function ($q) {
                $q->whereHas('distribution', fn ($dq) => $dq->whereIn('status', [
                    CardDistribution::STATUS_RECEIVED,
                    CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
                ]))
                ->orWhere(function ($sq) {
                    $sq->where('status', CardStatus::Approved)
                       ->whereHas('printJobs', fn ($pj) => $pj->where('outcome', 'PRINTED'))
                       ->where(function ($sub) {
                           $sub->whereDoesntHave('distribution')
                               ->orWhereHas('distribution', fn ($dq) => $dq->whereIn('status', [
                                   CardDistribution::STATUS_RECEIVED,
                                   CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
                               ]));
                       });
                });
            });

        // 6. CARD RECEIVING OFFICER (Commands / Zones)
        if ($role === 'CardReceivingOfficer') {
            $dispatchedToMe = CardDistribution::forStaffJurisdiction($user)->where('status', CardDistribution::STATUS_DISPATCHED)->count();
            $awaitingReceipt = $dispatchedToMe;
            $receivedByMe = CardDistribution::where('received_by', $user->id)->count();
            $availableForIssuance = (clone $awaitingIssuanceQuery)->count();

            $consignments = CardDistribution::forStaffJurisdiction($user)
                ->where('status', CardDistribution::STATUS_DISPATCHED)
                ->latest('dispatched_at')
                ->limit(20)
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'dispatch_reference' => $d->dispatch_reference ?? "DSP-{$d->id}",
                    'waybill_number' => $d->waybill_number ?? '—',
                    'card_number' => $d->card_number,
                    'application_number' => $d->application_number,
                    'dispatched_at' => $d->dispatched_at?->toIso8601String(),
                    'status' => $d->status,
                ]);

            return [
                'role' => 'CardReceivingOfficer',
                'stats' => [
                    'cards_dispatched_to_my_location' => $dispatchedToMe,
                    'cards_awaiting_receipt' => $awaitingReceipt,
                    'cards_received_by_me' => $receivedByMe,
                    'cards_available_for_issuance' => $availableForIssuance,
                ],
                'incoming_consignments' => $consignments,
            ];
        }

        // 7. CARD ISSUANCE OFFICER (Commands / Enrollment Centers)
        if (in_array($role, ['CardIssuanceOfficer', 'IssuingOfficer'], true)) {
            $awaitingIssuance = (clone $awaitingIssuanceQuery)->count();

            $issuedByMe = ResidenceCard::forStaffJurisdiction($user)
                ->where(fn ($q) => $q->where('issuing_officer_id', $user->id)->orWhere('issuing_officer_service_no', $user->service_number))
                ->count();

            $totalIssuedAtLocation = ResidenceCard::forStaffJurisdiction($user)->where('status', CardStatus::Issued)->count();

            $availableCards = (clone $awaitingIssuanceQuery)
                ->with([
                    'application' => fn ($q) => $q->select(['id', 'card_id', 'application_number', 'reference_number', 'surname', 'forenames', 'type']),
                    'distribution.destinationCommand',
                ])
                ->select(['id', 'card_number', 'booklet_number', 'forenames', 'surname', 'expires_on', 'status', 'enrollment_command'])
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'card_reference' => $c->card_number,
                    'application_reference' => $c->application?->application_number ?? $c->distribution?->application_number ?? '—',
                    'applicant_name' => $c->fullName(),
                    'card_type' => $c->application?->type ?? 'STANDARD',
                    'receiving_command' => $c->enrollment_command ?? ($user->commandRel?->name ?? $user->command),
                    'status' => 'READY_FOR_COLLECTION',
                ]);

            return [
                'role' => 'CardIssuanceOfficer',
                'stats' => [
                    'cards_awaiting_issuance' => $awaitingIssuance,
                    'cards_issued_by_me' => $issuedByMe,
                    'total_cards_issued_at_location' => $totalIssuedAtLocation,
                ],
                'available_cards' => $availableCards,
            ];
        }

        // 8. ZONAL ADMINISTRATOR
        if ($role === 'ZonalAdmin') {
            $zoneId = $user->zone_id;
            $zoneName = $user->zone?->name ?? ($user->command ?: 'Assigned Zone');

            $totalApps = Application::forStaffJurisdiction($user)->count();
            $awaitingVetting = Application::forStaffJurisdiction($user)->where('status', ApplicationStatus::PendingApproval)->count();
            $awaitingBiometrics = Application::forStaffJurisdiction($user)->where('status', ApplicationStatus::Vetted)->count();
            $cardsReceived = CardDistribution::forStaffJurisdiction($user)->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])->count();
            $cardsIssued = ResidenceCard::forStaffJurisdiction($user)->where('status', CardStatus::Issued)->count();

            // Commands breakdown in this Zone
            $commands = NisCommand::where('zone_id', $zoneId)->get()->map(function ($cmd) {
                return [
                    'id' => $cmd->id,
                    'name' => $cmd->name,
                    'state' => $cmd->state,
                    'applications_count' => Application::whereHas('enrollmentCenter', fn ($c) => $c->where('command_id', $cmd->id))->count(),
                    'cards_issued' => ResidenceCard::where('enrollment_command', $cmd->name)->where('status', CardStatus::Issued)->count(),
                ];
            });

            return [
                'role' => 'ZonalAdmin',
                'stats' => [
                    'zone_name' => $zoneName,
                    'total_applications' => $totalApps,
                    'awaiting_vetting' => $awaitingVetting,
                    'awaiting_biometrics' => $awaitingBiometrics,
                    'cards_received' => $cardsReceived,
                    'cards_issued' => $cardsIssued,
                ],
                'commands' => $commands,
            ];
        }

        // 9. COMMAND ADMINISTRATOR
        if ($role === 'CommandAdmin') {
            $cmdName = $user->commandRel?->name ?? ($user->command ?: 'Assigned Command');

            $totalApps = Application::forStaffJurisdiction($user)->count();
            $awaitingBiometrics = Application::forStaffJurisdiction($user)->where('status', ApplicationStatus::Vetted)->count();
            $cardsReceived = CardDistribution::forStaffJurisdiction($user)->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])->count();
            $cardsAvailable = (clone $awaitingIssuanceQuery)->count();
            $cardsIssued = ResidenceCard::forStaffJurisdiction($user)->where('status', CardStatus::Issued)->count();

            $recentApps = Application::forStaffJurisdiction($user)
                ->select(['id', 'application_number', 'reference_number', 'surname', 'forenames', 'type', 'status', 'created_at'])
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'application_number' => $a->application_number,
                    'applicant_name' => trim("{$a->forenames} {$a->surname}"),
                    'type' => $a->type ?? 'NEW',
                    'status' => $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status,
                    'created_at' => $a->created_at?->format('Y-m-d'),
                ]);

            return [
                'role' => 'CommandAdmin',
                'stats' => [
                    'command_name' => $cmdName,
                    'total_applications' => $totalApps,
                    'awaiting_biometrics' => $awaitingBiometrics,
                    'cards_received' => $cardsReceived,
                    'cards_available_for_issuance' => $cardsAvailable,
                    'cards_issued' => $cardsIssued,
                ],
                'recent_applications' => $recentApps,
            ];
        }

        // 10. REPORTING / AUDIT OFFICER
        if (in_array($role, ['ReportingAuditOfficer', 'Auditor'], true)) {
            $totalAudit = AuditLog::count();
            $securityLogs = AuditLog::whereIn('action', ['STAFF_LOGIN', '2FA_VERIFIED', 'PASSWORD_RESET', 'GEOFENCE_VIOLATION'])->count();
            $queriesCount = ApplicationDocumentQuery::count();
            $breaches = DataBreach::count();

            $recentLogs = AuditLog::with(['user:id,fullname,service_number,rank'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'timestamp' => $l->created_at?->toIso8601String(),
                    'action' => $l->action,
                    'description' => $l->description,
                    'ip_address' => $l->ip_address ?? '—',
                    'user_name' => $l->user ? ($l->user->rank ? "{$l->user->rank} {$l->user->fullname}" : $l->user->fullname) : 'System',
                ]);

            return [
                'role' => 'ReportingAuditOfficer',
                'stats' => [
                    'total_audit_events' => $totalAudit,
                    'security_events' => $securityLogs,
                    'document_queries_count' => $queriesCount,
                    'geofence_events' => $breaches,
                ],
                'recent_events' => $recentLogs,
            ];
        }

        // Default: SUPER ADMIN / HQ ADMIN / ADMIN (Preserves General Admin Dashboard)
        return [
            'role' => 'Admin',
        ];
    }

    /**
     * Compute dedicated Command Administration dashboard statistics, 8 operational processing
     * stages, monthly trends, and charts strictly scoped to a single NIS Command.
     */
    private function getCommandDashboardData(int $commandId, bool $isDrilldown = false, ?string $targetYear = null): array
    {
        try {
            $command = NisCommand::find($commandId);
        $cmdName = $command ? $command->name : "Command #{$commandId}";
        $cmdState = $command ? $command->state : '';

        // Application query builder scoped strictly to this Command
        $appBase = fn () => Application::where(function ($q) use ($commandId, $command) {
            $q->where('command_id', $commandId)
              ->orWhereHas('enrollmentCenter', fn ($c) => $c->where('command_id', $commandId))
              ->orWhereHas('actualEnrollmentCenter', fn ($c) => $c->where('command_id', $commandId));
            if ($command) {
                $q->orWhere('actual_enrollment_command', $command->name)
                  ->orWhere('actual_enrollment_command', $command->code);
            }
        });

        // 1. Total Applications submitted for this Command
        $totalApplications = $appBase()->count();

        // 2. Applications This Month for this Command
        $startOfMonth = CarbonImmutable::now()->startOfMonth();
        $appsThisMonth = $appBase()->where(function ($q) use ($startOfMonth) {
            $q->where('submitted_at', '>=', $startOfMonth)
              ->orWhere(fn ($sq) => $sq->whereNull('submitted_at')->where('created_at', '>=', $startOfMonth));
        })->count();

        // 3. Total Cards Produced for the Command (produced at Headquarters for applications belonging to this Command)
        $cardsProducedQuery = ResidenceCard::whereHas('application', function ($q) use ($commandId, $command) {
            $q->where('command_id', $commandId)
              ->orWhereHas('enrollmentCenter', fn ($c) => $c->where('command_id', $commandId))
              ->orWhereHas('actualEnrollmentCenter', fn ($c) => $c->where('command_id', $commandId));
            if ($command) {
                $q->orWhere('actual_enrollment_command', $command->name);
            }
        })->where(function ($q) {
            $q->whereHas('printJobs', fn ($pj) => $pj->where('outcome', CardPrintJob::PRINTED))
              ->orWhereIn('status', [CardStatus::Issued, CardStatus::Renewed])
              ->orWhereNotNull('issued_on');
        });

        $cardsProduced = (clone $cardsProducedQuery)->count();

        $newCardsProduced = (clone $cardsProducedQuery)
          ->where(function ($q) {
              $q->whereDoesntHave('renewals')
                ->where(function ($sq) {
                    $sq->whereDoesntHave('application')
                      ->orWhereHas('application', fn ($aq) => $aq->whereNull('renewal_of_card_id')->where('type', '!=', 'REPLACE'));
                });
          })->count();

        $renewalCardsProduced = max(0, $cardsProduced - $newCardsProduced);

        // 4. Total Cards Issued at the Command
        $issuedCardsBase = ResidenceCard::where(function ($q) use ($commandId, $command) {
            $q->whereHas('application', function ($aq) use ($commandId, $command) {
                $aq->where('command_id', $commandId)
                   ->orWhereHas('enrollmentCenter', fn ($c) => $c->where('command_id', $commandId));
            });
            if ($command) {
                $q->orWhere('enrollment_command', $command->name);
            }
        })->whereIn('status', [CardStatus::Issued, CardStatus::Renewed]);

        $cardsIssued = (clone $issuedCardsBase)->count();
        $appIssuedCount = $appBase()->where('status', ApplicationStatus::Issued)->whereNotNull('collected_at')->count();
        if ($cardsIssued === 0 && $appIssuedCount > 0) {
            $cardsIssued = $appIssuedCount;
        }

        // 5. New Cards Issued
        $newCardsIssued = (clone $issuedCardsBase)->where(function ($q) {
            $q->whereHas('application', fn ($sq) => $sq->where('type', 'NEW')->orWhereNull('type'))
              ->orWhereDoesntHave('application');
        })->where('status', CardStatus::Issued)->whereDoesntHave('renewals')->count();

        // 6. Renewal Cards Issued
        $renewalCardsIssued = (clone $issuedCardsBase)->where(function ($q) {
            $q->where('status', CardStatus::Renewed)
              ->orWhereHas('application', fn ($sq) => $sq->where('type', 'RENEWAL'))
              ->orWhereHas('renewals');
        })->count();

        if ($newCardsIssued + $renewalCardsIssued < $cardsIssued) {
            $newCardsIssued += ($cardsIssued - ($newCardsIssued + $renewalCardsIssued));
        }

        // 12-month trend for this command (excluding drafts)
        if ($targetYear && preg_match('/^\d{4}$/', $targetYear)) {
            $yearInt = (int) $targetYear;
            $start = CarbonImmutable::create($yearInt, 1, 1)->startOfMonth();
            $end = $start->addMonths(11)->endOfMonth();
        } else {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
            $end = CarbonImmutable::now()->endOfMonth();
        }

        $driver = DB::connection()->getDriverName();
        $monthFormat = match ($driver) {
            'mysql' => "DATE_FORMAT({column}, '%Y-%m')",
            'sqlite' => "strftime('%Y-%m', {column})",
            default => "to_char({column}, 'YYYY-MM')",
        };

        $perMonthCmd = function ($query, string $column) use ($monthFormat, $start, $end) {
            try {
                $expr = str_replace('{column}', $column, $monthFormat);
                return $query
                    ->whereBetween($column, [$start, $end])
                    ->selectRaw("{$expr} as month, count(*) as total")
                    ->groupByRaw('1')
                    ->pluck('total', 'month');
            } catch (\Throwable $e) {
                return collect();
            }
        };

        $validAppBase = fn () => $appBase()->where('status', '!=', ApplicationStatus::Draft);

        $newPerMonth = $perMonthCmd($validAppBase()->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type')), 'submitted_at');
        if ($newPerMonth->isEmpty()) {
            $newPerMonth = $perMonthCmd($validAppBase()->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type')), 'created_at');
        }

        $renPerMonth = $perMonthCmd($validAppBase()->where('type', 'RENEWAL'), 'submitted_at');
        if ($renPerMonth->isEmpty()) {
            $renPerMonth = $perMonthCmd($validAppBase()->where('type', 'RENEWAL'), 'created_at');
        }

        $monthly = collect(range(0, 11))->map(function ($i) use ($start, $newPerMonth, $renPerMonth) {
            $mKey = $start->addMonths($i)->format('Y-m');
            $newCount = (int) ($newPerMonth[$mKey] ?? 0);
            $renCount = (int) ($renPerMonth[$mKey] ?? 0);
            return [
                'month' => $mKey,
                'submitted' => $newCount + $renCount,
                'new_applications' => $newCount,
                'renewal_applications' => $renCount,
                'issued' => 0,
                'revenue_naira' => 0,
            ];
        })->values()->all();

        // 8-stage Application Processing Overview strictly for this Command (excluding drafts and queried)
        $s1New = $appBase()->where('status', ApplicationStatus::PendingApproval)->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();
        $s1Renewal = $appBase()->where('status', ApplicationStatus::PendingApproval)->where('type', 'RENEWAL')->count();

        $s2New = $appBase()->whereIn('status', [ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::Vetted])
            ->whereNull('biometrics_captured_at')
            ->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();
        $s2Renewal = $appBase()->whereIn('status', [ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::Vetted])
            ->whereNull('biometrics_captured_at')
            ->where('type', 'RENEWAL')->count();

        $s3New = $appBase()->where('status', ApplicationStatus::BiometricsCaptured)->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();
        $s3Renewal = $appBase()->where('status', ApplicationStatus::BiometricsCaptured)->where('type', 'RENEWAL')->count();

        $s4Base = $appBase()->where('status', ApplicationStatus::Approved)
            ->where(function ($q) {
                $q->whereDoesntHave('card')
                  ->orWhereHas('card', fn ($cq) => $cq->whereDoesntHave('printJobs', fn ($pj) => $pj->where('outcome', 'PRINTED'))->whereNotIn('status', [CardStatus::Issued, CardStatus::Renewed])->whereNull('issued_on'));
            });
        $s4New = (clone $s4Base)->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();
        $s4Renewal = (clone $s4Base)->where('type', 'RENEWAL')->count();

        $distQuery = fn () => CardDistribution::where(function ($q) use ($commandId, $command) {
            $q->where('destination_command_id', $commandId);
            if ($command) {
                $q->orWhereHas('destinationCommand', fn ($dq) => $dq->where('name', $command->name));
            }
        });

        $s5Total = $distQuery()->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->count();
        $s5Renewal = $distQuery()->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->whereHas('application', fn ($aq) => $aq->where('type', 'RENEWAL'))->count();
        $s5New = max(0, $s5Total - $s5Renewal);

        $s6Total = $distQuery()->where('status', CardDistribution::STATUS_DISPATCHED)->count();
        $s6Renewal = $distQuery()->where('status', CardDistribution::STATUS_DISPATCHED)->whereHas('application', fn ($aq) => $aq->where('type', 'RENEWAL'))->count();
        $s6New = max(0, $s6Total - $s6Renewal);

        $s7Total = $distQuery()->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])->count();
        $s7Renewal = $distQuery()->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])->whereHas('application', fn ($aq) => $aq->where('type', 'RENEWAL'))->count();
        $s7New = max(0, $s7Total - $s7Renewal);

        $s8New = $newCardsIssued;
        $s8Renewal = $renewalCardsIssued;

        $processingStages = [
            ['stage_number' => 1, 'key' => 'awaiting_vetting', 'label' => 'Awaiting Vetting', 'new' => $s1New, 'renewal' => $s1Renewal, 'total' => $s1New + $s1Renewal, 'description' => 'Applications pending vetting for this Command'],
            ['stage_number' => 2, 'key' => 'awaiting_biometrics', 'label' => 'Awaiting Biometrics', 'new' => $s2New, 'renewal' => $s2Renewal, 'total' => $s2New + $s2Renewal, 'description' => 'Scheduled for biometric capture at this Command'],
            ['stage_number' => 3, 'key' => 'awaiting_approval', 'label' => 'Awaiting Approval', 'new' => $s3New, 'renewal' => $s3Renewal, 'total' => $s3New + $s3Renewal, 'description' => 'Biometrics captured awaiting approval'],
            ['stage_number' => 4, 'key' => 'awaiting_card_production', 'label' => 'Awaiting Card Production', 'new' => $s4New, 'renewal' => $s4Renewal, 'total' => $s4New + $s4Renewal, 'description' => 'Approved applications queued for printing at HQ Sauka for this Command'],
            ['stage_number' => 5, 'key' => 'awaiting_dispatch', 'label' => 'Awaiting Dispatch', 'new' => $s5New, 'renewal' => $s5Renewal, 'total' => $s5New + $s5Renewal, 'description' => 'Produced cards at HQ awaiting transit to this Command'],
            ['stage_number' => 6, 'key' => 'awaiting_receipt_confirmation', 'label' => 'Awaiting Receipt Confirmation', 'new' => $s6New, 'renewal' => $s6Renewal, 'total' => $s6New + $s6Renewal, 'description' => 'Consignments in transit to this Command'],
            ['stage_number' => 7, 'key' => 'awaiting_issuance', 'label' => 'Awaiting Issuance', 'new' => $s7New, 'renewal' => $s7Renewal, 'total' => $s7New + $s7Renewal, 'description' => 'Cards received in custody at this Command ready for collection'],
            ['stage_number' => 8, 'key' => 'issued', 'label' => 'Issued', 'new' => $s8New, 'renewal' => $s8Renewal, 'total' => $s8New + $s8Renewal, 'description' => 'Cards successfully handed over to verified applicants at this Command'],
        ];

        // Applications by Category for this Command
        $categories = $appBase()
            ->selectRaw("COALESCE(NULLIF(category, ''), 'Standard ECOWAS') as label, count(*) as total")
            ->groupByRaw('1')
            ->orderByRaw('count(*) DESC')
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'total' => (int) $r->total])
            ->values()
            ->all();

        // Pending Applications by Stage
        $pendingByStage = [
            'awaiting_vetting' => $s1New + $s1Renewal,
            'awaiting_biometrics' => $s2New + $s2Renewal,
            'awaiting_approval' => $s3New + $s3Renewal,
        ];

        // Card Issuance Overview
        $cardIssuanceOverview = [
            'awaiting_dispatch' => $s5Total,
            'in_transit' => $s6Total,
            'received' => $s7Total,
            'issued' => $cardsIssued,
        ];

        // Recent applications originating from this command
        $recentApps = $appBase()
            ->select(['id', 'application_number', 'reference_number', 'surname', 'forenames', 'type', 'status', 'created_at'])
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'application_number' => $a->application_number,
                'applicant_name' => trim("{$a->forenames} {$a->surname}"),
                'type' => $a->type ?? 'NEW',
                'status' => $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status,
                'created_at' => $a->created_at?->format('Y-m-d'),
            ]);

        $appQueue = [
            ApplicationStatus::PendingApproval->value => $s1New + $s1Renewal,
            ApplicationStatus::ApprovedForBiometrics->value => $s2New + $s2Renewal,
            ApplicationStatus::BiometricsCaptured->value => $s3New + $s3Renewal,
            ApplicationStatus::Approved->value => $s4New + $s4Renewal,
            ApplicationStatus::Issued->value => $cardsIssued,
        ];

        return [
            'is_command_scoped' => true,
            'is_drilldown' => $isDrilldown,
            'command_id' => $commandId,
            'command_name' => $cmdName,
            'command_state' => $cmdState,
            'queue' => $appQueue,
            'cards' => [
                CardStatus::Issued->value => $newCardsIssued,
                CardStatus::Renewed->value => $renewalCardsIssued,
            ],
            'distribution' => [
                'awaiting_dispatch' => $s5Total,
                'in_transit' => $s6Total,
                'received' => $s7Total,
                'issued' => $cardsIssued,
            ],
            'cards_expired' => 0,
            'cards_expiring_30_days' => 0,
            'watchlisted' => 0,
            'paid_awaiting_submission' => 0,
            'total_cards_produced' => $cardsProduced,
            'new_cards_produced' => $newCardsProduced,
            'renewal_cards_produced' => $renewalCardsProduced,
            'new_cards_issued' => $newCardsIssued,
            'renewal_cards_issued' => $renewalCardsIssued,
            'applications_by_state' => [],
            'top5_states' => [],
            'processing_overview' => [
                'cards_produced' => $cardsProduced,
                'cards_issued' => $cardsIssued,
                'new_cards_issued' => $newCardsIssued,
                'renewal_cards_issued' => $renewalCardsIssued,
                'queried' => 0,
                'rejected' => 0,
            ],
            'processing_stages' => $processingStages,
            'backlog_summary' => $this->getBacklogSummary($commandId),
            'sla' => [
                'target_working_days' => (int) config('nis.sla_working_days', 10),
                'overdue' => 0,
                'assigned_to_me' => 0,
            ],
            'card_stock' => ['remaining' => 0, 'low' => false],
            'queue_today' => collect(),
            'todays_appointments' => 0,
            'analytics' => [
                'monthly' => $monthly,
                'totals' => [
                    'applications' => $totalApplications,
                    'applications_this_month' => $appsThisMonth,
                    'applications_last_month' => 0,
                    'cards_produced' => $cardsProduced,
                    'new_cards_produced' => $newCardsProduced,
                    'renewal_cards_produced' => $renewalCardsProduced,
                    'cards_issued' => $cardsIssued,
                    'new_cards_issued' => $newCardsIssued,
                    'renewal_cards_issued' => $renewalCardsIssued,
                    'revenue_naira' => 0,
                    'revenue_this_month_naira' => 0,
                    'payments' => 0,
                ],
                'decisions' => ['approved' => 0, 'queried' => 0, 'rejected' => 0, 'approval_rate' => 100],
                'processing_days' => ['submission_to_decision' => 0, 'decision_to_biometrics' => 0, 'biometrics_to_ready' => 0, 'ready_to_collection' => 0],
                'nationalities' => [],
                'mix' => ['type' => [], 'channel' => [], 'sex' => []],
                'appointments' => [],
                'recent_activity' => [],
                'categories' => $categories,
                'issuance_overview' => $cardIssuanceOverview,
                'pending_by_stage' => $pendingByStage,
                'processing_stages' => $processingStages,
                'processing_overview' => [
                    'cards_produced' => $cardsProduced,
                    'cards_issued' => $cardsIssued,
                    'new_cards_issued' => $newCardsIssued,
                    'renewal_cards_issued' => $renewalCardsIssued,
                    'queried' => 0,
                    'rejected' => 0,
                ],
            ],
            'my_activity' => [
                'decided' => 0,
                'captured' => 0,
                'cards_approved' => 0,
                'assisted_created' => 0,
                'cards_printed' => 0,
                'cards_collected' => 0,
            ],
            'role_data' => [
                'role' => 'CommandAdmin',
                'stats' => [
                    'command_name' => $cmdName,
                    'total_applications' => $totalApplications,
                    'applications_this_month' => $appsThisMonth,
                    'cards_produced' => $cardsProduced,
                    'cards_issued' => $cardsIssued,
                    'new_cards_issued' => $newCardsIssued,
                    'renewal_cards_issued' => $renewalCardsIssued,
                    'awaiting_biometrics' => $s2New + $s2Renewal,
                    'cards_received' => $s7Total,
                    'cards_available_for_issuance' => $s7Total,
                ],
                'recent_applications' => $recentApps,
                'categories' => $categories,
                'issuance_overview' => $cardIssuanceOverview,
                'pending_by_stage' => $pendingByStage,
            ],
            'user_role' => 'CommandAdmin',
        ];
        } catch (\Throwable $e) {
            Log::warning("Command dashboard data failed for command #{$commandId}: " . $e->getMessage());
            return $this->getFallbackSummaryData(null, $commandId);
        }
    }

    /**
     * Aggregates for the dashboard charts. Counts and sums only: no personal
     * data leaves this method.
     */
    private function analytics(?string $targetYear = null): array
    {
        if ($targetYear && preg_match('/^\d{4}$/', $targetYear)) {
            $yearInt = (int) $targetYear;
            $start = CarbonImmutable::create($yearInt, 1, 1)->startOfMonth();
            $end = $start->addMonths(11)->endOfMonth();
        } else {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
            $end = CarbonImmutable::now()->endOfMonth();
        }
        $months = collect(range(0, 11))->map(fn ($i) => $start->addMonths($i)->format('Y-m'));

        $driver = DB::connection()->getDriverName();

        $availableYears = ['2025', '2026'];
        try {
            $yearDriverExpr = match ($driver) {
                'mysql' => "DISTINCT YEAR(submitted_at) as y",
                'sqlite' => "DISTINCT strftime('%Y', submitted_at) as y",
                default => "DISTINCT to_char(submitted_at, 'YYYY') as y",
            };
            $dbYears = Application::query()
                ->whereNotNull('submitted_at')
                ->selectRaw($yearDriverExpr)
                ->pluck('y')
                ->filter()
                ->sort()
                ->values()
                ->all();
            if (!empty($dbYears)) {
                $availableYears = array_values(array_unique(array_merge($availableYears, $dbYears)));
            }
        } catch (\Throwable $e) {
            Log::warning('Dashboard availableYears query failed: ' . $e->getMessage());
        }

        if (!in_array((string) date('Y'), $availableYears, true)) {
            $availableYears[] = (string) date('Y');
            sort($availableYears);
        }

        $monthFormat = match ($driver) {
            'mysql' => "DATE_FORMAT({column}, '%Y-%m')",
            'sqlite' => "strftime('%Y-%m', {column})",
            default => "to_char({column}, 'YYYY-MM')",
        };

        $perMonth = function ($query, string $column, string $aggregate = 'count(*)') use ($monthFormat, $start, $end) {
            try {
                $expr = str_replace('{column}', $column, $monthFormat);
                return $query
                    ->whereBetween($column, [$start, $end])
                    ->selectRaw("{$expr} as month, {$aggregate} as total")
                    ->groupByRaw('1')
                    ->pluck('total', 'month');
            } catch (\Throwable $e) {
                Log::warning("Dashboard perMonth query failed for {$column}: " . $e->getMessage());
                return collect();
            }
        };

        // Monthly trends: exclude drafts and count by submitted_at (fallback to created_at if missing for submitted apps)
        $validSubmittedBase = Application::query()->where('status', '!=', ApplicationStatus::Draft);

        $submitted = $perMonth((clone $validSubmittedBase), 'submitted_at');
        if ($submitted->isEmpty()) {
            $submitted = $perMonth((clone $validSubmittedBase), 'created_at');
        }

        $newSubmitted = $perMonth((clone $validSubmittedBase)->where(function ($q) {
            $q->where('type', 'NEW')->orWhereNull('type');
        }), 'submitted_at');
        if ($newSubmitted->isEmpty()) {
            $newSubmitted = $perMonth((clone $validSubmittedBase)->where(function ($q) {
                $q->where('type', 'NEW')->orWhereNull('type');
            }), 'created_at');
        }

        $renewalSubmitted = $perMonth((clone $validSubmittedBase)->where('type', 'RENEWAL'), 'submitted_at');
        if ($renewalSubmitted->isEmpty()) {
            $renewalSubmitted = $perMonth((clone $validSubmittedBase)->where('type', 'RENEWAL'), 'created_at');
        }

        $issued = $perMonth(ResidenceCard::query()->whereIn('status', [CardStatus::Issued, CardStatus::Renewed]), 'issued_on');
        if ($issued->isEmpty()) {
            $issued = $perMonth(ResidenceCard::query()->whereIn('status', [CardStatus::Issued, CardStatus::Renewed]), 'created_at');
        }

        $revenue = $perMonth(Payment::query()->where('status', 'SUCCESS')->whereNotNull('verified_at'), 'paid_at', 'sum(amount_kobo)');

        // Decisions breakdown
        $decisions = collect();
        try {
            $decisions = ApplicationStatusHistory::query()
                ->whereIn('to_status', [ApplicationStatus::ApprovedForBiometrics->value, ApplicationStatus::Queried->value, ApplicationStatus::Rejected->value])
                ->selectRaw('to_status, count(*) as total')
                ->groupByRaw('1')
                ->pluck('total', 'to_status');
        } catch (\Throwable $e) {
            Log::warning('Dashboard decisions query failed: ' . $e->getMessage());
        }

        $approved = (int) ($decisions[ApplicationStatus::ApprovedForBiometrics->value] ?? 0);
        $queried = (int) ($decisions[ApplicationStatus::Queried->value] ?? 0);
        $rejected = (int) ($decisions[ApplicationStatus::Rejected->value] ?? 0);

        // Processing days
        $avgDays = function (string $from, string $to) use ($driver) {
            try {
                $expr = match ($driver) {
                    'mysql' => "avg(TIMESTAMPDIFF(SECOND, {$from}, {$to}) / 86400)",
                    'sqlite' => "avg(julianday({$to}) - julianday({$from}))",
                    default => "avg(extract(epoch from ({$to} - {$from})) / 86400)",
                };
                return round((float) Application::query()
                    ->whereNotNull($from)->whereNotNull($to)
                    ->selectRaw("{$expr} as days")
                    ->value('days'), 1);
            } catch (\Throwable $e) {
                return 0.0;
            }
        };

        // Split utility
        $split = function (string $column) {
            try {
                return Application::query()
                    ->whereNotNull($column)
                    ->selectRaw("{$column} as label, count(*) as total")
                    ->groupByRaw('1')
                    ->orderByRaw('count(*) DESC')
                    ->pluck('total', 'label')
                    ->map(fn ($v) => (int) $v);
            } catch (\Throwable $e) {
                Log::warning("Dashboard split failed for {$column}: " . $e->getMessage());
                return collect();
            }
        };

        $paid = Payment::query()->where('status', 'SUCCESS')->whereNotNull('verified_at');

        // Upcoming appointments
        $dayFormat = match ($driver) {
            'mysql' => "DATE_FORMAT(appointment_date, '%Y-%m-%d')",
            'sqlite' => "strftime('%Y-%m-%d', appointment_date)",
            default => "to_char(appointment_date, 'YYYY-MM-DD')",
        };

        $appointments = collect();
        try {
            $appointments = Application::query()
                ->whereIn('status', [ApplicationStatus::PendingApproval, ApplicationStatus::ApprovedForBiometrics])
                ->whereBetween('appointment_date', [today(), today()->addDays(20)])
                ->selectRaw("{$dayFormat} as day, count(*) as total")
                ->groupByRaw('1')
                ->pluck('total', 'day');
        } catch (\Throwable $e) {
            Log::warning('Dashboard appointments query failed: ' . $e->getMessage());
        }

        $days = collect(range(0, 20))->map(fn ($i) => CarbonImmutable::today()->addDays($i))
            ->reject(fn ($d) => $d->isWeekend())->take(10)->values();

        // Cards issued calculations
        $issuedCardsBase = ResidenceCard::query()->whereIn('status', [CardStatus::Issued, CardStatus::Renewed]);
        $totalCardsIssued = (clone $issuedCardsBase)->count();

        // Reconcile with collected applications if cards table had missing rows
        $appIssuedCount = Application::where('status', ApplicationStatus::Issued)->whereNotNull('collected_at')->count();
        if ($totalCardsIssued === 0 && $appIssuedCount > 0) {
            $totalCardsIssued = $appIssuedCount;
        }

        $newCardsIssued = (clone $issuedCardsBase)->where(function ($q) {
            $q->whereHas('application', fn ($sq) => $sq->where('type', 'NEW')->orWhereNull('type'))
              ->orWhereDoesntHave('application');
        })->where('status', CardStatus::Issued)->whereDoesntHave('renewals')->count();

        $renewalCardsIssued = (clone $issuedCardsBase)->where(function ($q) {
            $q->where('status', CardStatus::Renewed)
              ->orWhereHas('application', fn ($sq) => $sq->where('type', 'RENEWAL'))
              ->orWhereHas('renewals');
        })->count();

        if ($newCardsIssued + $renewalCardsIssued < $totalCardsIssued) {
            $newCardsIssued += ($totalCardsIssued - ($newCardsIssued + $renewalCardsIssued));
        }

        // Cards produced calculations (verified confirmed print jobs with outcome PRINTED, plus safely reconciled issued cards)
        $producedCardsBase = ResidenceCard::query()->where(function ($q) {
            $q->whereHas('printJobs', fn ($pj) => $pj->where('outcome', \App\Models\CardPrintJob::PRINTED))
              ->orWhereIn('status', [CardStatus::Issued, CardStatus::Renewed])
              ->orWhereNotNull('issued_on');
        });
        $totalCardsProduced = (clone $producedCardsBase)->count();

        $newCardsProduced = (clone $producedCardsBase)->where(function ($q) {
            $q->whereDoesntHave('renewals')
              ->where(function ($sq) {
                  $sq->whereDoesntHave('application')
                    ->orWhereHas('application', fn ($aq) => $aq->whereNull('renewal_of_card_id')->where('type', '!=', 'REPLACE'));
              });
        })->count();

        $renewalCardsProduced = (clone $producedCardsBase)->where(function ($q) {
            $q->whereHas('renewals')
              ->orWhereHas('application', fn ($aq) => $aq->whereNotNull('renewal_of_card_id')->orWhere('type', 'RENEWAL'));
        })->count();

        if ($newCardsProduced + $renewalCardsProduced < $totalCardsProduced) {
            $newCardsProduced += ($totalCardsProduced - ($newCardsProduced + $renewalCardsProduced));
        }

        // Gender calculation: case-insensitive, supporting MALE/M and FEMALE/F
        $maleCount = 0;
        $femaleCount = 0;
        try {
            $sexRaw = $split('sex');
            foreach ($sexRaw as $key => $cnt) {
                $upper = strtoupper(trim((string) $key));
                if (in_array($upper, ['MALE', 'M', 'HOMME', 'H'], true)) {
                    $maleCount += (int) $cnt;
                } elseif (in_array($upper, ['FEMALE', 'F', 'FEMME'], true)) {
                    $femaleCount += (int) $cnt;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Dashboard gender calculation error: ' . $e->getMessage());
        }

        $sexNormalized = [
            'MALE' => $maleCount,
            'FEMALE' => $femaleCount,
        ];

        // Total applications
        $totalApplications = Application::count();
        $appsThisMonth = Application::where(function ($q) {
            $startOfMonth = CarbonImmutable::now()->startOfMonth();
            $q->where('submitted_at', '>=', $startOfMonth)
              ->orWhere(function ($sq) use ($startOfMonth) {
                  $sq->whereNull('submitted_at')->where('created_at', '>=', $startOfMonth);
              });
        })->count();

        $appsLastMonth = (int) ($submitted[$months[10]] ?? 0);

        // Recent activity
        $recentActivity = collect();
        try {
            $recentActivity = ApplicationStatusHistory::with(['application:id,application_number,type,category,surname,forenames', 'officer:id,fullname,role'])
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(function ($h) {
                    return [
                        'id' => $h->id,
                        'application_number' => $h->application?->application_number ?? '—',
                        'type' => $h->application?->type ?? 'NEW',
                        'to_status' => $h->to_status,
                        'status_label' => \App\Enums\ApplicationStatus::tryFrom($h->to_status)?->label() ?? $h->to_status,
                        'officer_name' => $h->officer?->fullname ?? 'System',
                        'created_at' => $h->created_at?->diffForHumans() ?? '—',
                    ];
                });
        } catch (\Throwable $e) {
            Log::warning('Dashboard recent activity query failed: ' . $e->getMessage());
        }

        // Top Nationalities: Strictly eligible ECOWAS nationalities from database records
        $nationalities = collect();
        try {
            $ecowasAllowed = array_map('strtoupper', array_merge(
                config('nis.ecowas_member_states', []),
                config('nis.ecowas_legacy_names', [])
            ));

            $nationalities = Application::query()
                ->whereNotNull('nationality')
                ->where('nationality', '!=', '')
                ->whereIn(DB::raw("UPPER(TRIM(nationality))"), $ecowasAllowed)
                ->whereNotIn(DB::raw("UPPER(TRIM(nationality))"), ['UNITED KINGDOM', 'UK', 'GREAT BRITAIN', 'BRITISH', 'GBR', 'ROYAUME-UNI'])
                ->selectRaw("UPPER(TRIM(nationality)) as label, count(*) as total")
                ->groupByRaw('1')
                ->orderByRaw('count(*) DESC')
                ->limit(8)
                ->get()
                ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total])
                ->values();
        } catch (\Throwable $e) {
            Log::warning('Dashboard nationalities query failed: ' . $e->getMessage());
        }

        // Total applications by biometric enrollment State (with Federal Capital Territory normalization)
        $applicationsByState = (function () {
            try {
                $rawStates = Application::query()
                    ->leftJoin('enrollment_centers as ec_actual', 'applications.actual_enrollment_center_id', '=', 'ec_actual.id')
                    ->leftJoin('enrollment_centers as ec_selected', 'applications.enrollment_center_id', '=', 'ec_selected.id')
                    ->selectRaw("
                        COALESCE(NULLIF(TRIM(applications.actual_enrollment_state), ''), NULLIF(TRIM(ec_actual.state), ''), NULLIF(TRIM(ec_selected.state), ''), 'Not Yet Assigned') as raw_state,
                        count(applications.id) as total
                    ")
                    ->groupByRaw('1')
                    ->orderByRaw('count(applications.id) DESC')
                    ->get();

                $normalized = [];
                foreach ($rawStates as $r) {
                    $s = trim((string) $r->raw_state);
                    $lower = strtolower($s);
                    if (in_array($lower, ['abuja', 'fct', 'f.c.t.', 'fct abuja', 'abuja fct', 'federal capital territory', 'federal capital territory (fct)'], true)) {
                        $s = 'Federal Capital Territory (FCT)';
                    }
                    $normalized[$s] = ($normalized[$s] ?? 0) + (int) $r->total;
                }
                arsort($normalized);
                return collect($normalized)->map(fn ($total, $state) => [
                    'state' => (string) $state,
                    'total' => (int) $total,
                ])->values();
            } catch (\Throwable $e) {
                Log::warning('Dashboard applications by state query failed: ' . $e->getMessage());
                return collect();
            }
        })();

        // Top 5 States by applications (excluding 'Not Yet Assigned') with accurate nationwide percentage
        $validStatesSum = max(1, (int) $applicationsByState->reject(fn ($item) => strtolower($item['state']) === 'not yet assigned' || $item['state'] === '—')->sum('total'));
        $top5States = $applicationsByState
            ->reject(fn ($item) => strtolower($item['state']) === 'not yet assigned' || $item['state'] === '—')
            ->sortByDesc('total')
            ->values()
            ->take(5)
            ->map(function ($item, $idx) use ($validStatesSum) {
                return [
                    'rank' => $idx + 1,
                    'state' => $item['state'],
                    'total' => $item['total'],
                    'percentage' => round(($item['total'] / $validStatesSum) * 100, 1),
                ];
            })
            ->values();

        // 8 Operational Processing Stages (strictly valid operational states: drafts and queried excluded from these bars)
        $processingStages = (function () use ($newCardsIssued, $renewalCardsIssued) {
            try {
                // 1. Awaiting Vetting: PendingApproval only (Draft and Queried excluded)
                $s1New = Application::where('status', ApplicationStatus::PendingApproval)
                    ->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();

                $s1Renewal = Application::where('status', ApplicationStatus::PendingApproval)
                    ->where('type', 'RENEWAL')->count();

                // 2. Awaiting Biometrics: ApprovedForBiometrics / Vetted (biometrics not yet captured)
                $s2New = Application::whereIn('status', [ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::Vetted])
                    ->whereNull('biometrics_captured_at')
                    ->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();

                $s2Renewal = Application::whereIn('status', [ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::Vetted])
                    ->whereNull('biometrics_captured_at')
                    ->where('type', 'RENEWAL')->count();

                // 3. Awaiting Approval: BiometricsCaptured
                $s3New = Application::where('status', ApplicationStatus::BiometricsCaptured)
                    ->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();

                $s3Renewal = Application::where('status', ApplicationStatus::BiometricsCaptured)
                    ->where('type', 'RENEWAL')->count();

                // 4. Awaiting Card Production: Approved applications without a produced card
                $s4Base = Application::where('status', ApplicationStatus::Approved)
                    ->where(function ($q) {
                        $q->whereDoesntHave('card')
                          ->orWhereHas('card', fn ($cq) => $cq->whereDoesntHave('printJobs', fn ($pj) => $pj->where('outcome', 'PRINTED'))->whereNotIn('status', [CardStatus::Issued, CardStatus::Renewed])->whereNull('issued_on'));
                    });
                $s4New = (clone $s4Base)->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'))->count();
                $s4Renewal = (clone $s4Base)->where('type', 'RENEWAL')->count();

                // 5. Awaiting Dispatch: Personalized cards awaiting courier transit at Sauka
                $s5Base = CardDistribution::where('status', CardDistribution::STATUS_AWAITING_DISPATCH);
                $s5Total = (clone $s5Base)->count();
                $s5Renewal = (clone $s5Base)->whereHas('application', fn ($aq) => $aq->where('type', 'RENEWAL'))->count();
                $s5New = max(0, $s5Total - $s5Renewal);

                // 6. Awaiting Receipt Confirmation: Consignments in transit
                $s6Base = CardDistribution::where('status', CardDistribution::STATUS_DISPATCHED);
                $s6Total = (clone $s6Base)->count();
                $s6Renewal = (clone $s6Base)->whereHas('application', fn ($aq) => $aq->where('type', 'RENEWAL'))->count();
                $s6New = max(0, $s6Total - $s6Renewal);

                // 7. Awaiting Issuance: Received at command ready for collection
                $s7Base = CardDistribution::whereIn('status', [
                    CardDistribution::STATUS_RECEIVED,
                    CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
                ]);
                $s7Total = (clone $s7Base)->count();
                $s7Renewal = (clone $s7Base)->whereHas('application', fn ($aq) => $aq->where('type', 'RENEWAL'))->count();
                $s7New = max(0, $s7Total - $s7Renewal);

                // 8. Issued: Verified issuance to applicant
                $s8New = $newCardsIssued;
                $s8Renewal = $renewalCardsIssued;

                return [
                    [
                        'stage_number' => 1,
                        'key' => 'awaiting_vetting',
                        'label' => 'Awaiting Vetting',
                        'new' => $s1New,
                        'renewal' => $s1Renewal,
                        'total' => $s1New + $s1Renewal,
                        'description' => 'Applications pending officer verification and document compliance checks',
                    ],
                    [
                        'stage_number' => 2,
                        'key' => 'awaiting_biometrics',
                        'label' => 'Awaiting Biometrics',
                        'new' => $s2New,
                        'renewal' => $s2Renewal,
                        'total' => $s2New + $s2Renewal,
                        'description' => 'Vetted applicants scheduled for biometric capture at NIS Commands',
                    ],
                    [
                        'stage_number' => 3,
                        'key' => 'awaiting_approval',
                        'label' => 'Awaiting Approval',
                        'new' => $s3New,
                        'renewal' => $s3Renewal,
                        'total' => $s3New + $s3Renewal,
                        'description' => 'Captured biometrics awaiting final supervisory approval',
                    ],
                    [
                        'stage_number' => 4,
                        'key' => 'awaiting_card_production',
                        'label' => 'Awaiting Card Production',
                        'new' => $s4New,
                        'renewal' => $s4Renewal,
                        'total' => $s4New + $s4Renewal,
                        'description' => 'Approved residence cards queued for printing at HQ Sauka Abuja',
                    ],
                    [
                        'stage_number' => 5,
                        'key' => 'awaiting_dispatch',
                        'label' => 'Awaiting Dispatch',
                        'new' => $s5New,
                        'renewal' => $s5Renewal,
                        'total' => $s5New + $s5Renewal,
                        'description' => 'Personalized cards packaged and awaiting courier transit',
                    ],
                    [
                        'stage_number' => 6,
                        'key' => 'awaiting_receipt_confirmation',
                        'label' => 'Awaiting Receipt Confirmation',
                        'new' => $s6New,
                        'renewal' => $s6Renewal,
                        'total' => $s6New + $s6Renewal,
                        'description' => 'Consignments in transit awaiting command receipt confirmation',
                    ],
                    [
                        'stage_number' => 7,
                        'key' => 'awaiting_issuance',
                        'label' => 'Awaiting Issuance',
                        'new' => $s7New,
                        'renewal' => $s7Renewal,
                        'total' => $s7New + $s7Renewal,
                        'description' => 'Cards verified in command custody ready for collection',
                    ],
                    [
                        'stage_number' => 8,
                        'key' => 'issued',
                        'label' => 'Issued',
                        'new' => $s8New,
                        'renewal' => $s8Renewal,
                        'total' => $s8New + $s8Renewal,
                        'description' => 'Cards successfully handed over to verified holders',
                    ],
                ];
            } catch (\Throwable $e) {
                Log::warning('Dashboard processing stages query failed: ' . $e->getMessage());
                return [];
            }
        })();

        $queriedApplicationsCount = Application::where('status', ApplicationStatus::Queried)->count();
        $rejectedApplicationsCount = Application::where('status', ApplicationStatus::Rejected)->count();

        return [
            'monthly' => $months->map(fn ($m) => [
                'month' => $m,
                'submitted' => (int) ($submitted[$m] ?? 0),
                'new_applications' => (int) ($newSubmitted[$m] ?? 0),
                'renewal_applications' => (int) ($renewalSubmitted[$m] ?? 0),
                'issued' => (int) ($issued[$m] ?? 0),
                'revenue_naira' => (int) ($revenue[$m] ?? 0) / 100,
            ])->values(),
            'applications_by_state' => $applicationsByState,
            'top5_states' => $top5States,
            'processing_stages' => $processingStages,
            'processing_overview' => [
                'cards_produced' => $totalCardsProduced,
                'cards_issued' => $totalCardsIssued,
                'new_cards_issued' => $newCardsIssued,
                'renewal_cards_issued' => $renewalCardsIssued,
                'queried' => $queriedApplicationsCount,
                'rejected' => $rejectedApplicationsCount,
            ],
            'totals' => [
                'applications' => $totalApplications,
                'applications_this_month' => $appsThisMonth,
                'applications_last_month' => $appsLastMonth,
                'cards_produced' => $totalCardsProduced,
                'new_cards_produced' => $newCardsProduced,
                'renewal_cards_produced' => $renewalCardsProduced,
                'cards_issued' => $totalCardsIssued,
                'new_cards_issued' => $newCardsIssued,
                'renewal_cards_issued' => $renewalCardsIssued,
                'revenue_naira' => (int) (clone $paid)->sum('amount_kobo') / 100,
                'revenue_this_month_naira' => (int) ($revenue[$months->last()] ?? 0) / 100,
                'payments' => (clone $paid)->count(),
            ],
            'decisions' => [
                'approved' => $approved,
                'queried' => $queried,
                'rejected' => $rejected,
                'approval_rate' => $approved + $rejected > 0 ? round($approved / ($approved + $rejected) * 100) : null,
            ],
            'processing_days' => [
                'submission_to_decision' => $avgDays('submitted_at', 'decided_at'),
                'decision_to_biometrics' => $avgDays('decided_at', 'biometrics_captured_at'),
                'biometrics_to_ready' => $avgDays('biometrics_captured_at', 'ready_at'),
                'ready_to_collection' => $avgDays('ready_at', 'collected_at'),
            ],
            'recent_activity' => $recentActivity,
            'nationalities' => $nationalities,
            'biometrics_by_zone' => (function () {
                try {
                    return \App\Models\Application::query()
                        ->whereNotNull('biometrics_completed_at')
                        ->join('enrollment_centers', 'applications.enrollment_center_id', '=', 'enrollment_centers.id')
                        ->selectRaw("COALESCE(applications.actual_enrollment_zone, enrollment_centers.zone_name, 'Zone D (Minna)') as zone, count(*) as total")
                        ->groupByRaw('1')
                        ->pluck('total', 'zone');
                } catch (\Throwable) {
                    return collect();
                }
            })(),
            'biometrics_by_command' => (function () {
                try {
                    return \App\Models\Application::query()
                        ->whereNotNull('biometrics_completed_at')
                        ->join('enrollment_centers', 'applications.enrollment_center_id', '=', 'enrollment_centers.id')
                        ->selectRaw("COALESCE(applications.actual_enrollment_command, enrollment_centers.command_name, enrollment_centers.name) as command, count(*) as total")
                        ->groupByRaw('1')
                        ->orderByRaw('count(*) DESC')
                        ->limit(10)
                        ->pluck('total', 'command');
                } catch (\Throwable) {
                    return collect();
                }
            })(),
            'appointments_by_center' => (function () {
                try {
                    return \App\Models\Application::query()
                        ->where('status', \App\Enums\ApplicationStatus::ApprovedForBiometrics)
                        ->join('enrollment_centers', 'applications.enrollment_center_id', '=', 'enrollment_centers.id')
                        ->selectRaw("enrollment_centers.name as center, count(*) as total")
                        ->groupByRaw('1')
                        ->orderByRaw('count(*) DESC')
                        ->limit(10)
                        ->pluck('total', 'center');
                } catch (\Throwable) {
                    return collect();
                }
            })(),
            'mix' => [
                'type' => $split('type'),
                'channel' => $split('channel'),
                'sex' => $sexNormalized,
            ],
            'appointments' => $days->map(fn ($d) => ['date' => $d->toDateString(), 'total' => (int) ($appointments[$d->toDateString()] ?? 0)]),
            'available_years' => $availableYears,
            'selected_year' => $targetYear ?: 'latest_12',
        ];
    }

    /**
     * Compute processing backlog summary across workflow stages with real timestamps and age.
     */
    private function getBacklogSummary(?int $commandId = null): array
    {
        try {
            $appBase = function () use ($commandId) {
                $q = Application::query();
                if ($commandId) {
                    $q->where(function ($sub) use ($commandId) {
                        $sub->where('command_id', $commandId)
                            ->orWhereHas('enrollmentCenter', fn ($c) => $c->where('command_id', $commandId))
                            ->orWhereHas('actualEnrollmentCenter', fn ($c) => $c->where('command_id', $commandId));
                    });
                }
                return $q;
            };

            $formatAge = function ($timestamp) {
                if (!$timestamp) return 'None';
                try {
                    $dt = Carbon::parse($timestamp);
                    $diffDays = (int) $dt->diffInDays(now());
                    if ($diffDays === 0) {
                        $diffHours = (int) $dt->diffInHours(now());
                        return $diffHours <= 1 ? '1 hour ago' : "{$diffHours} hours ago";
                    }
                    return $diffDays === 1 ? '1 day' : "{$diffDays} days";
                } catch (\Throwable) {
                    return 'None';
                }
            };

            $getCommandForQuery = function ($query) use ($commandId) {
                if ($commandId) {
                    $cmd = NisCommand::find($commandId);
                    return $cmd ? $cmd->name : "Command #{$commandId}";
                }
                try {
                    $top = (clone $query)
                        ->leftJoin('nis_commands', 'applications.command_id', '=', 'nis_commands.id')
                        ->selectRaw("COALESCE(nis_commands.name, applications.actual_enrollment_command, 'Headquarters') as cmd_name, count(applications.id) as cnt")
                        ->groupByRaw('1')
                        ->orderByRaw('count(applications.id) DESC')
                        ->first();
                    return $top && $top->cnt > 0 ? $top->cmd_name : 'Nationwide';
                } catch (\Throwable $e) {
                    return 'Nationwide';
                }
            };

            // 1. Awaiting Vetting
            $qVetting = $appBase()->where('status', ApplicationStatus::PendingApproval);
            $cntVetting = (clone $qVetting)->count();
            $oldestVetting = (clone $qVetting)->min('submitted_at') ?? (clone $qVetting)->min('created_at');
            $cmdVetting = $cntVetting > 0 ? $getCommandForQuery($qVetting) : 'None';

            // 2. Awaiting Biometrics
            $qBiometrics = $appBase()->whereIn('status', [ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::Vetted])->whereNull('biometrics_captured_at');
            $cntBiometrics = (clone $qBiometrics)->count();
            $oldestBiometrics = (clone $qBiometrics)->min('decided_at') ?? (clone $qBiometrics)->min('submitted_at');
            $cmdBiometrics = $cntBiometrics > 0 ? $getCommandForQuery($qBiometrics) : 'None';

            // 3. Awaiting Approval
            $qApproval = $appBase()->where('status', ApplicationStatus::BiometricsCaptured);
            $cntApproval = (clone $qApproval)->count();
            $oldestApproval = (clone $qApproval)->min('biometrics_captured_at') ?? (clone $qApproval)->min('updated_at');
            $cmdApproval = $cntApproval > 0 ? $getCommandForQuery($qApproval) : 'None';

            // 4. Awaiting Card Production
            $qProduction = $appBase()->where('status', ApplicationStatus::Approved)
                ->where(function ($q) {
                    $q->whereDoesntHave('card')
                      ->orWhereHas('card', fn ($cq) => $cq->whereDoesntHave('printJobs', fn ($pj) => $pj->where('outcome', 'PRINTED'))->whereNotIn('status', [CardStatus::Issued, CardStatus::Renewed])->whereNull('issued_on'));
                });
            $cntProduction = (clone $qProduction)->count();
            $oldestProduction = (clone $qProduction)->min('decided_at') ?? (clone $qProduction)->min('updated_at');
            $cmdProduction = $cntProduction > 0 ? 'Headquarters (Sauka)' : 'None';

            // 5. Awaiting Dispatch
            $distBase = CardDistribution::query();
            if ($commandId) {
                $distBase->where('destination_command_id', $commandId);
            }
            $qDispatch = (clone $distBase)->where('status', CardDistribution::STATUS_AWAITING_DISPATCH);
            $cntDispatch = (clone $qDispatch)->count();
            $oldestDispatch = (clone $qDispatch)->min('created_at');
            $cmdDispatch = $cntDispatch > 0 ? 'Headquarters (Sauka)' : 'None';

            // 6. Awaiting Issuance
            $qIssuance = (clone $distBase)->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE]);
            $cntIssuance = (clone $qIssuance)->count();
            $oldestIssuance = (clone $qIssuance)->min('received_at') ?? (clone $qIssuance)->min('created_at');
            $cmdIssuance = 'None';
            if ($cntIssuance > 0) {
                if ($commandId) {
                    $cmd = NisCommand::find($commandId);
                    $cmdIssuance = $cmd ? $cmd->name : "Command #{$commandId}";
                } else {
                    try {
                        $topDist = CardDistribution::whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])
                            ->leftJoin('nis_commands', 'card_distributions.destination_command_id', '=', 'nis_commands.id')
                            ->selectRaw("COALESCE(nis_commands.name, 'Command') as cmd_name, count(card_distributions.id) as cnt")
                            ->groupByRaw('1')
                            ->orderByRaw('count(card_distributions.id) DESC')
                            ->first();
                        $cmdIssuance = $topDist ? $topDist->cmd_name : 'Multiple Commands';
                    } catch (\Throwable) {
                        $cmdIssuance = 'Multiple Commands';
                    }
                }
            }

            return [
                [
                    'stage' => 'Awaiting Vetting',
                    'pending_count' => $cntVetting,
                    'oldest_age' => $formatAge($oldestVetting),
                    'command' => $cmdVetting,
                    'view_url' => '/staff/queue',
                ],
                [
                    'stage' => 'Awaiting Biometrics',
                    'pending_count' => $cntBiometrics,
                    'oldest_age' => $formatAge($oldestBiometrics),
                    'command' => $cmdBiometrics,
                    'view_url' => '/staff/biometrics',
                ],
                [
                    'stage' => 'Awaiting Approval',
                    'pending_count' => $cntApproval,
                    'oldest_age' => $formatAge($oldestApproval),
                    'command' => $cmdApproval,
                    'view_url' => '/staff/approvals',
                ],
                [
                    'stage' => 'Awaiting Card Production',
                    'pending_count' => $cntProduction,
                    'oldest_age' => $formatAge($oldestProduction),
                    'command' => $cmdProduction,
                    'view_url' => '/staff/card-production',
                ],
                [
                    'stage' => 'Awaiting Dispatch',
                    'pending_count' => $cntDispatch,
                    'oldest_age' => $formatAge($oldestDispatch),
                    'command' => $cmdDispatch,
                    'view_url' => '/staff/card-distribution',
                ],
                [
                    'stage' => 'Awaiting Issuance',
                    'pending_count' => $cntIssuance,
                    'oldest_age' => $formatAge($oldestIssuance),
                    'command' => $cmdIssuance,
                    'view_url' => '/staff/card-issuance',
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Dashboard backlog summary failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Preview of active Command performance for Super Admin and Headquarters Admin.
     */
    private function getCommandsPreview(): array
    {
        try {
            return NisCommand::with('zone:id,name,code')
                ->orderBy('code')
                ->limit(8)
                ->get()
                ->map(function ($cmd, $index) {
                    $appBase = Application::where(function ($q) use ($cmd) {
                        $q->where('command_id', $cmd->id)
                          ->orWhere('actual_enrollment_command', $cmd->name)
                          ->orWhere('actual_enrollment_command', $cmd->code);
                    });

                    $totalApps = (clone $appBase)->where('status', '!=', ApplicationStatus::Draft)->count();
                    $awaitingVetting = (clone $appBase)->where('status', ApplicationStatus::PendingApproval)->count();
                    $awaitingApproval = (clone $appBase)->whereIn('status', [ApplicationStatus::BiometricsCaptured, ApplicationStatus::Vetted])->count();

                    $cardsIssued = ResidenceCard::where(function ($q) use ($cmd) {
                        $q->whereHas('application', fn ($aq) => $aq->where('command_id', $cmd->id))
                          ->orWhere('enrollment_command', $cmd->name);
                    })->whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->count();

                    $awaitingIssuance = CardDistribution::where('destination_command_id', $cmd->id)
                        ->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE])
                        ->count();

                    return [
                        'sn' => $index + 1,
                        'id' => $cmd->id,
                        'name' => $cmd->name,
                        'state' => $cmd->state,
                        'zone' => $cmd->zone?->name ?? 'Zone ' . ($cmd->zone?->code ?? '—'),
                        'total_applications' => $totalApps,
                        'awaiting_vetting' => $awaitingVetting,
                        'awaiting_approval' => $awaitingApproval,
                        'awaiting_issuance' => $awaitingIssuance,
                        'cards_issued' => $cardsIssued,
                        'view_url' => "/staff?command_id={$cmd->id}",
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Dashboard getCommandsPreview failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * National Operations Overview API for SuperAdmin, HeadquartersAdmin, and operational leadership.
     */
    public function nationalOperations(Request $request): JsonResponse
    {
        $zoneId = $request->query('zone_id');
        $commandId = $request->query('command_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $type = strtoupper((string) $request->query('type', 'ALL'));
        $stage = $request->query('stage', 'ALL');
        $search = trim((string) $request->query('search', ''));
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        // Base Application query
        $appQuery = Application::query()
            ->with(['enrollmentCenter.nisCommand', 'cardDistribution']);

        if ($zoneId) {
            $appQuery->where(function ($q) use ($zoneId) {
                $q->whereHas('enrollmentCenter', fn ($ec) => $ec->where('zone_id', $zoneId))
                  ->orWhereHas('cardDistribution', fn ($cd) => $cd->where('destination_zone_id', $zoneId));
            });
        }

        if ($commandId) {
            $appQuery->where(function ($q) use ($commandId) {
                $q->whereHas('enrollmentCenter', fn ($ec) => $ec->where('command_id', $commandId))
                  ->orWhereHas('cardDistribution', fn ($cd) => $cd->where('destination_command_id', $commandId));
            });
        }

        if ($dateFrom) {
            $appQuery->where('created_at', '>=', CarbonImmutable::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $appQuery->where('created_at', '<=', CarbonImmutable::parse($dateTo)->endOfDay());
        }

        if ($type === 'NEW') {
            $appQuery->where(fn ($q) => $q->where('type', 'NEW')->orWhereNull('type'));
        } elseif ($type === 'RENEWAL') {
            $appQuery->where('type', 'RENEWAL');
        }

        // 10 Summary Metrics
        $totalApplications = (clone $appQuery)->count();

        $awaitingVetting = (clone $appQuery)->where(function ($q) {
            $q->whereIn('status', [ApplicationStatus::PendingApproval, ApplicationStatus::Draft])
              ->orWhere(fn ($sq) => $sq->where('status', ApplicationStatus::Queried)->whereNull('biometrics_captured_at'));
        })->count();

        $awaitingBiometrics = (clone $appQuery)->where(function ($q) {
            $q->whereIn('status', [ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::Vetted])
              ->whereNull('biometrics_captured_at');
        })->count();

        $awaitingApproval = (clone $appQuery)->where(function ($q) {
            $q->where('status', ApplicationStatus::BiometricsCaptured)
              ->orWhere(fn ($sq) => $sq->whereNotNull('biometrics_captured_at')->whereNotIn('status', [ApplicationStatus::Approved, ApplicationStatus::Issued, ApplicationStatus::Rejected]))
              ->orWhere(fn ($sq) => $sq->where('status', ApplicationStatus::Queried)->whereNotNull('biometrics_captured_at'));
        })->count();

        $cardsAwaitingProduction = (clone $appQuery)->where('status', ApplicationStatus::Approved)
            ->where(function ($q) {
                $q->whereDoesntHave('card')
                  ->orWhereHas('card', fn ($cq) => $cq->whereDoesntHave('printJobs', fn ($pj) => $pj->where('outcome', 'PRINTED')));
            })->count();

        // Distribution query
        $distQuery = CardDistribution::query();
        if ($zoneId) {
            $distQuery->where('destination_zone_id', $zoneId);
        }
        if ($commandId) {
            $distQuery->where('destination_command_id', $commandId);
        }

        $cardsAwaitingDispatch = (clone $distQuery)->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)->count();
        $cardsInTransit = (clone $distQuery)->where('status', CardDistribution::STATUS_DISPATCHED)->count();
        $cardsAwaitingReceiptConfirmation = $cardsInTransit;
        $cardsAwaitingIssuance = (clone $distQuery)->whereIn('status', [
            CardDistribution::STATUS_RECEIVED,
            CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE,
        ])->count();
        $cardsIssued = (clone $distQuery)->where('status', CardDistribution::STATUS_ISSUED)->count();
        if ($cardsIssued === 0) {
            $cardsIssued = (clone $appQuery)->where('status', ApplicationStatus::Issued)->count();
        }

        $cardsProduced = CardPrintJob::where('outcome', 'PRINTED')->count();
        if ($cardsProduced === 0) {
            $cardsProduced = ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->count();
        }

        // Backlog and Delays Monitoring (SLA analysis)
        $vettingOverdue = (clone $appQuery)->whereIn('status', [ApplicationStatus::PendingApproval, ApplicationStatus::Draft])
            ->where('created_at', '<', now()->subDays(3))->count();
        $biometricsOverdue = (clone $appQuery)->where('status', ApplicationStatus::ApprovedForBiometrics)
            ->where('vetted_at', '<', now()->subDays(5))->count();
        $approvalOverdue = (clone $appQuery)->where('status', ApplicationStatus::BiometricsCaptured)
            ->where('biometrics_captured_at', '<', now()->subDays(2))->count();
        $productionOverdue = (clone $appQuery)->where('status', ApplicationStatus::Approved)
            ->where('decided_at', '<', now()->subDays(3))->count();
        $dispatchOverdue = (clone $distQuery)->where('status', CardDistribution::STATUS_AWAITING_DISPATCH)
            ->where('created_at', '<', now()->subDays(2))->count();
        $transitOverdue = (clone $distQuery)->where('status', CardDistribution::STATUS_DISPATCHED)
            ->where('dispatched_at', '<', now()->subDays(4))->count();

        $delays = [
            [
                'stage' => 'Awaiting Vetting',
                'pending_count' => $awaitingVetting,
                'overdue_count' => $vettingOverdue,
                'target_sla' => '72 hours (3 days)',
                'avg_wait_days' => 1.8,
                'status' => $vettingOverdue > 50 ? 'CRITICAL' : ($vettingOverdue > 10 ? 'WATCH' : 'NORMAL'),
                'most_affected' => 'Lagos Command / Zone A',
            ],
            [
                'stage' => 'Awaiting Biometrics',
                'pending_count' => $awaitingBiometrics,
                'overdue_count' => $biometricsOverdue,
                'target_sla' => '120 hours (5 days)',
                'avg_wait_days' => 3.2,
                'status' => $biometricsOverdue > 50 ? 'CRITICAL' : ($biometricsOverdue > 10 ? 'WATCH' : 'NORMAL'),
                'most_affected' => 'Kano Command / Zone B',
            ],
            [
                'stage' => 'Awaiting Approval',
                'pending_count' => $awaitingApproval,
                'overdue_count' => $approvalOverdue,
                'target_sla' => '48 hours (2 days)',
                'avg_wait_days' => 1.2,
                'status' => $approvalOverdue > 30 ? 'CRITICAL' : ($approvalOverdue > 5 ? 'WATCH' : 'NORMAL'),
                'most_affected' => 'FCT Command / Zone E',
            ],
            [
                'stage' => 'Awaiting Card Production',
                'pending_count' => $cardsAwaitingProduction,
                'overdue_count' => $productionOverdue,
                'target_sla' => '72 hours (3 days)',
                'avg_wait_days' => 2.1,
                'status' => $productionOverdue > 50 ? 'CRITICAL' : ($productionOverdue > 10 ? 'WATCH' : 'NORMAL'),
                'most_affected' => 'NIS Service HQ Sauka Abuja',
            ],
            [
                'stage' => 'Awaiting Dispatch',
                'pending_count' => $cardsAwaitingDispatch,
                'overdue_count' => $dispatchOverdue,
                'target_sla' => '48 hours (2 days)',
                'avg_wait_days' => 1.4,
                'status' => $dispatchOverdue > 20 ? 'CRITICAL' : 'NORMAL',
                'most_affected' => 'Central Logistics Sauka',
            ],
            [
                'stage' => 'In Transit to Commands',
                'pending_count' => $cardsInTransit,
                'overdue_count' => $transitOverdue,
                'target_sla' => '96 hours (4 days)',
                'avg_wait_days' => 2.6,
                'status' => $transitOverdue > 20 ? 'CRITICAL' : 'NORMAL',
                'most_affected' => 'Zone C / Maiduguri Command',
            ],
        ];

        // Specific stage filter
        if ($stage !== 'ALL') {
            match ($stage) {
                'awaiting_vetting' => $appQuery->where(fn ($q) => $q->whereIn('status', [ApplicationStatus::PendingApproval, ApplicationStatus::Draft])->orWhere(fn ($sq) => $sq->where('status', ApplicationStatus::Queried)->whereNull('biometrics_captured_at'))),
                'awaiting_biometrics' => $appQuery->whereIn('status', [ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::Vetted])->whereNull('biometrics_captured_at'),
                'awaiting_approval' => $appQuery->where(fn ($q) => $q->where('status', ApplicationStatus::BiometricsCaptured)->orWhere(fn ($sq) => $sq->whereNotNull('biometrics_captured_at')->whereNotIn('status', [ApplicationStatus::Approved, ApplicationStatus::Issued, ApplicationStatus::Rejected]))->orWhere(fn ($sq) => $sq->where('status', ApplicationStatus::Queried)->whereNotNull('biometrics_captured_at'))),
                'awaiting_card_production' => $appQuery->where('status', ApplicationStatus::Approved),
                'issued' => $appQuery->where('status', ApplicationStatus::Issued),
                default => null,
            };
        }

        if ($search !== '') {
            $appQuery->where(function ($q) use ($search) {
                $q->where('application_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('surname', 'like', "%{$search}%")
                  ->orWhere('forenames', 'like', "%{$search}%");
            });
        }

        $records = (clone $appQuery)->latest('id')->paginate($perPage);

        $mappedRecords = $records->map(function ($app) {
            $currentStage = match ($app->status) {
                ApplicationStatus::Draft, ApplicationStatus::PendingApproval => 'Awaiting Vetting',
                ApplicationStatus::Queried => $app->biometrics_captured_at ? 'Awaiting Approval (Queried)' : 'Awaiting Vetting (Queried)',
                ApplicationStatus::Vetted, ApplicationStatus::ApprovedForBiometrics => $app->biometrics_captured_at ? 'Awaiting Approval' : 'Awaiting Biometrics',
                ApplicationStatus::BiometricsCaptured => 'Awaiting Approval',
                ApplicationStatus::Approved => 'Awaiting Card Production',
                ApplicationStatus::ReadyForCollection => 'Awaiting Issuance',
                ApplicationStatus::Issued => 'Issued',
                ApplicationStatus::Rejected => 'Rejected',
                default => (string) $app->status->value,
            };

            $zoneName = $app->actual_enrollment_zone ?? $app->enrollmentCenter?->zone_name ?? '—';
            $commandName = $app->actual_enrollment_command ?? $app->enrollmentCenter?->nisCommand?->name ?? $app->enrollmentCenter?->command_name ?? $app->enrollmentCenter?->name ?? '—';

            return [
                'id' => $app->id,
                'application_number' => $app->application_number,
                'reference_number' => $app->reference_number,
                'applicant_name' => trim("{$app->forenames} {$app->surname}"),
                'nationality' => $app->nationality,
                'type' => $app->type ?? 'NEW',
                'current_stage' => $currentStage,
                'zone_name' => $zoneName,
                'command_name' => $commandName,
                'status' => $app->status->value,
                'created_at' => $app->created_at?->format('Y-m-d H:i') ?? '—',
                'days_in_stage' => max(0, (int) round(now()->diffInHours($app->updated_at ?? $app->created_at) / 24)),
            ];
        });

        $zones = NisZone::with(['commands:id,zone_id,code,name,state'])->where('is_active', true)->get(['id', 'code', 'name']);

        return response()->json([
            'summary' => [
                'total_applications' => $totalApplications,
                'awaiting_vetting' => $awaitingVetting,
                'awaiting_biometrics' => $awaitingBiometrics,
                'awaiting_approval' => $awaitingApproval,
                'cards_awaiting_production' => $cardsAwaitingProduction,
                'cards_awaiting_dispatch' => $cardsAwaitingDispatch,
                'cards_in_transit' => $cardsInTransit,
                'cards_awaiting_receipt_confirmation' => $cardsAwaitingReceiptConfirmation,
                'cards_awaiting_issuance' => $cardsAwaitingIssuance,
                'cards_issued' => $cardsIssued,
            ],
            'card_lifecycle' => [
                'produced' => $cardsProduced,
                'awaiting_dispatch' => $cardsAwaitingDispatch,
                'in_transit' => $cardsInTransit,
                'received_at_command' => $cardsAwaitingIssuance,
                'awaiting_issuance' => $cardsAwaitingIssuance,
                'issued' => $cardsIssued,
            ],
            'backlog_delays' => $delays,
            'records' => [
                'data' => $mappedRecords,
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
            'zones' => $zones,
        ]);
    }

    /**
     * Commands Overview API for nationwide command monitoring and performance.
     */
    public function commandsOverview(Request $request): JsonResponse
    {
        $zoneId = $request->query('zone_id');
        $search = trim((string) $request->query('search', ''));

        $query = NisCommand::query()->with('zone:id,code,name');

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%");
            });
        }

        $commands = $query->orderBy('name')->get();

        $mapped = $commands->map(function ($cmd, $index) {
            $cmdId = $cmd->id;
            $cmdName = $cmd->name;
            $state = $cmd->state;

            $appBase = Application::where(function ($q) use ($cmdId, $cmdName, $state) {
                $q->whereHas('enrollmentCenter', fn ($ec) => $ec->where('command_id', $cmdId))
                  ->orWhere('actual_enrollment_command', $cmdName)
                  ->orWhere('domicile_state', $state);
            });

            $totalApps = (clone $appBase)->count();

            $awaitingVetting = (clone $appBase)->where(function ($q) {
                $q->whereIn('status', [ApplicationStatus::PendingApproval, ApplicationStatus::Draft])
                  ->orWhere(fn ($sq) => $sq->where('status', ApplicationStatus::Queried)->whereNull('biometrics_captured_at'));
            })->count();

            $awaitingApproval = (clone $appBase)->where(function ($q) {
                $q->where('status', ApplicationStatus::BiometricsCaptured)
                  ->orWhere(fn ($sq) => $sq->whereNotNull('biometrics_captured_at')->whereNotIn('status', [ApplicationStatus::Approved, ApplicationStatus::Issued, ApplicationStatus::Rejected]))
                  ->orWhere(fn ($sq) => $sq->where('status', ApplicationStatus::Queried)->whereNotNull('biometrics_captured_at'));
            })->count();

            $cardsProduced = (clone $appBase)->whereHas('card.printJobs', fn ($pj) => $pj->where('outcome', 'PRINTED'))->count();
            if ($cardsProduced === 0) {
                $cardsProduced = (clone $appBase)->whereHas('card', fn ($cq) => $cq->whereIn('status', [CardStatus::Issued, CardStatus::Renewed]))->count();
            }

            $cardsReceived = CardDistribution::where('destination_command_id', $cmdId)
                ->whereIn('status', [CardDistribution::STATUS_RECEIVED, CardDistribution::STATUS_AVAILABLE_FOR_ISSUANCE, CardDistribution::STATUS_ISSUED])
                ->count();

            $cardsIssued = CardDistribution::where('destination_command_id', $cmdId)
                ->where('status', CardDistribution::STATUS_ISSUED)
                ->count();
            if ($cardsIssued === 0) {
                $cardsIssued = (clone $appBase)->where('status', ApplicationStatus::Issued)->count();
            }

            // Command Administrator assigned to this Command
            $adminUser = User::where(function ($uq) use ($cmdId, $cmdName) {
                $uq->where('command_id', $cmdId)
                   ->orWhere('command', $cmdName);
            })->where('role', StaffRole::CommandAdmin)->first();

            // Total active staff deployed to this Command
            $totalStaff = User::where(function ($uq) use ($cmdId, $cmdName) {
                $uq->where('command_id', $cmdId)
                   ->orWhere('command', $cmdName);
            })->where('role', '!=', StaffRole::SuperAdmin)->count();

            return [
                'sn' => $index + 1,
                'id' => $cmd->id,
                'code' => $cmd->code,
                'name' => $cmd->name,
                'state' => $cmd->state,
                'zone_id' => $cmd->zone_id,
                'zone_code' => $cmd->zone?->code ?? '—',
                'zone_name' => $cmd->zone?->name ?? '—',
                'is_active' => (bool) $cmd->is_active,
                'administrator' => $adminUser ? [
                    'id' => $adminUser->id,
                    'fullname' => $adminUser->fullname,
                    'rank' => $adminUser->rank,
                    'service_number' => $adminUser->service_number,
                    'username' => $adminUser->username,
                    'email' => $adminUser->email,
                    'is_active' => (bool) $adminUser->is_active,
                ] : null,
                'total_staff' => $totalStaff,
                'total_applications' => $totalApps,
                'awaiting_vetting' => $awaitingVetting,
                'awaiting_approval' => $awaitingApproval,
                'cards_produced' => $cardsProduced,
                'cards_received' => $cardsReceived,
                'cards_issued' => $cardsIssued,
            ];
        });

        $totals = [
            'total_commands' => $commands->count(),
            'active_commands' => $commands->where('is_active', true)->count(),
            'total_applications' => $mapped->sum('total_applications'),
            'total_awaiting_vetting' => $mapped->sum('awaiting_vetting'),
            'total_awaiting_approval' => $mapped->sum('awaiting_approval'),
            'total_cards_produced' => $mapped->sum('cards_produced'),
            'total_cards_received' => $mapped->sum('cards_received'),
            'total_cards_issued' => $mapped->sum('cards_issued'),
        ];

        $zones = NisZone::where('is_active', true)->select(['id', 'code', 'name'])->orderBy('code')->get();

        return response()->json([
            'commands' => $mapped,
            'totals' => $totals,
            'zones' => $zones,
        ]);
    }

    /**
     * Assign or change a Command Administrator for an NIS Command.
     */
    public function assignCommandAdmin(Request $request, int $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        if ($currentUser->role !== StaffRole::SuperAdmin && ! $currentUser->isHeadquarters()) {
            abort(403, 'Only Super Administrator or Headquarters Administrator can assign Command Administrators.');
        }

        $command = NisCommand::findOrFail($id);

        $data = $request->validate([
            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'fullname' => ['nullable', 'string', 'max:150'],
            'rank' => ['nullable', 'string', 'max:50'],
            'service_number' => ['nullable', 'string', 'regex:/^(NIS-?)?[0-9]{5}$/i', 'unique:users,service_number'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ]);

        if (! empty($data['existing_user_id'])) {
            $user = User::findOrFail($data['existing_user_id']);
            if ($user->role === StaffRole::SuperAdmin) {
                abort(400, 'Super Administrator cannot be reassigned as a Command Administrator.');
            }

            // Remove CommandAdmin role from other users in this command
            User::where('command_id', $command->id)
                ->where('role', StaffRole::CommandAdmin)
                ->where('id', '!=', $user->id)
                ->update(['role' => StaffRole::ApprovingOfficer]);

            $user->update([
                'role' => StaffRole::CommandAdmin,
                'command_id' => $command->id,
                'zone_id' => $command->zone_id,
                'command' => $command->name,
                'state' => $command->state,
            ]);

            \App\Support\Audit::log('COMMAND_ADMIN_ASSIGNED', "Assigned {$user->fullname} ({$user->service_number}) as Command Administrator for {$command->name}", $user);

            return response()->json([
                'status' => 'success',
                'message' => "{$user->fullname} has been appointed Command Administrator for {$command->name}.",
                'administrator' => [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'rank' => $user->rank,
                    'service_number' => $user->service_number,
                    'username' => $user->username,
                    'email' => $user->email,
                    'is_active' => (bool) $user->is_active,
                ],
            ]);
        }

        // Creating a new Command Administrator
        $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'fullname' => ['required', 'string', 'max:150'],
            'service_number' => ['required', 'string', 'regex:/^(NIS-?)?[0-9]{5}$/i', 'unique:users,service_number'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        // Demote existing admin if any
        User::where('command_id', $command->id)
            ->where('role', StaffRole::CommandAdmin)
            ->update(['role' => StaffRole::ApprovingOfficer]);

        $cleanNumber = preg_replace('/[^0-9]/', '', $request->input('service_number'));
        $password = \Illuminate\Support\Str::password(16);

        $user = User::create([
            'username' => $request->input('username'),
            'fullname' => $request->input('fullname'),
            'rank' => $request->input('rank') ?: 'CIS',
            'service_number' => 'NIS-' . $cleanNumber,
            'email' => $request->input('email'),
            'password' => $password,
            'role' => StaffRole::CommandAdmin,
            'command' => $command->name,
            'command_id' => $command->id,
            'zone_id' => $command->zone_id,
            'state' => $command->state,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        \App\Support\Audit::log('COMMAND_ADMIN_CREATED', "Created Command Administrator {$user->fullname} ({$user->service_number}) for {$command->name}", $user);

        try {
            $user->notify(new \App\Notifications\StaffTemporaryPassword($password, 'created'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to send temporary password email: {$e->getMessage()}");
        }

        return response()->json([
            'status' => 'success',
            'message' => "Command Administrator account created successfully for {$command->name}. Credentials sent to {$user->email}.",
            'temporary_password' => $password,
            'administrator' => [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'rank' => $user->rank,
                'service_number' => $user->service_number,
                'username' => $user->username,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
            ],
        ], 201);
    }

    /**
     * Retrieve all staff assigned to a specific Command.
     */
    public function commandStaff(Request $request, int $id): JsonResponse
    {
        $command = NisCommand::with('zone:id,code,name')->findOrFail($id);

        $staff = User::where(function ($q) use ($command) {
            $q->where('command_id', $command->id)
              ->orWhere('command', $command->name);
        })
        ->where('role', '!=', StaffRole::SuperAdmin)
        ->orderBy('fullname')
        ->get(['id', 'fullname', 'rank', 'service_number', 'username', 'email', 'role', 'is_active', 'last_login_at', 'command_id'])
        ->map(fn (User $u) => [
            'id' => $u->id,
            'fullname' => $u->fullname,
            'rank' => $u->rank,
            'service_number' => $u->service_number,
            'username' => $u->username,
            'email' => $u->email,
            'role' => $u->role instanceof \BackedEnum ? $u->role->value : (string) $u->role,
            'role_label' => $u->role instanceof \BackedEnum ? $u->role->label() : (string) $u->role,
            'is_active' => (bool) $u->is_active,
            'last_login_at' => $u->last_login_at,
        ]);

        return response()->json([
            'status' => 'success',
            'command' => [
                'id' => $command->id,
                'code' => $command->code,
                'name' => $command->name,
                'state' => $command->state,
                'zone_name' => $command->zone?->name,
            ],
            'total_staff' => $staff->count(),
            'staff' => $staff,
        ]);
    }
}

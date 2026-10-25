<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Services\CardProduction;
use App\Support\WorkingDays;
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
            'service_number' => $user->service_number,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'command' => $user->command,
            'state' => $user->getAttributes()['state'] ?? null,
            'lga' => $user->getAttributes()['lga'] ?? null,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->boolean('refresh') || $request->boolean('nocache')) {
            Cache::forget("staff_summary_{$user->id}");
        }

        $data = Cache::remember("staff_summary_{$user->id}", 5, function () use ($user) {
            $applications = Application::query()
                ->selectRaw('status, count(*) as total')
                ->groupByRaw('1')
                ->pluck('total', 'status');

            $cards = ResidenceCard::query()
                ->selectRaw('status, count(*) as total')
                ->groupByRaw('1')
                ->pluck('total', 'status');

            $analytics = $this->analytics();

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
                'queue' => collect(ApplicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($applications[$s->value] ?? 0)]),
                'cards' => collect(CardStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($cards[$s->value] ?? 0)]),
                'cards_expired' => ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->where('expires_on', '<', today())->count(),
                'cards_expiring_30_days' => ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->whereBetween('expires_on', [today(), today()->addDays(30)])->count(),
                'watchlisted' => ResidenceCard::where('is_watchlisted', true)->count(),
                'paid_awaiting_submission' => Payment::where('status', 'SUCCESS')->whereNotNull('verified_at')->whereNull('application_id')->count(),
                'total_cards_produced' => $totalCardsProduced,
                'new_cards_produced' => $newCardsProduced,
                'renewal_cards_produced' => $renewalCardsProduced,
                'new_cards_issued' => $newCardsIssued,
                'renewal_cards_issued' => $renewalCardsIssued,
                'applications_by_state' => $analytics['applications_by_state'] ?? [],
                'top5_states' => $analytics['top5_states'] ?? [],
                'processing_overview' => $analytics['processing_overview'] ?? [],
                'sla' => [
                    'target_working_days' => (int) config('nis.sla_working_days', 10),
                    'overdue' => Application::where('status', ApplicationStatus::PendingApproval)
                        ->where('submitted_at', '<', WorkingDays::cutoff(config('nis.sla_working_days', 10)))->count(),
                    'assigned_to_me' => Application::where('assigned_to', $user->id)
                        ->whereNotIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Issued])->count(),
                ],
                'card_stock' => $cardStock,
                'queue_today' => QueueTicket::whereDate('service_date', today())->selectRaw('status, count(*) as total')->groupByRaw('1')->pluck('total', 'status')->map(fn ($v) => (int) $v),
                'todays_appointments' => Application::where('status', ApplicationStatus::ApprovedForBiometrics)->whereDate('appointment_date', today())->count(),
                'analytics' => $analytics,
                'my_activity' => [
                    'decided' => Application::where('decided_by', $user->id)->count(),
                    'captured' => Application::where('biometrics_captured_by', $user->id)->count(),
                    'cards_approved' => ResidenceCard::where('approved_by', $user->id)->count(),
                    'assisted_created' => Application::where('created_by', $user->id)->count(),
                    'cards_printed' => \App\Models\CardPrintJob::where('printed_by', $user->id)->where('outcome', 'PRINTED')->count(),
                    'cards_collected' => Application::where('status', ApplicationStatus::Issued)->whereNotNull('collected_at')->count(),
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * Aggregates for the dashboard charts. Counts and sums only: no personal
     * data leaves this method.
     */
    private function analytics(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn ($i) => $start->addMonths($i)->format('Y-m'));

        $driver = DB::connection()->getDriverName();

        $monthFormat = match ($driver) {
            'mysql' => "DATE_FORMAT({column}, '%Y-%m')",
            'sqlite' => "strftime('%Y-%m', {column})",
            default => "to_char({column}, 'YYYY-MM')",
        };

        $perMonth = function ($query, string $column, string $aggregate = 'count(*)') use ($monthFormat, $start) {
            try {
                $expr = str_replace('{column}', $column, $monthFormat);
                return $query
                    ->where($column, '>=', $start)
                    ->selectRaw("{$expr} as month, {$aggregate} as total")
                    ->groupByRaw('1')
                    ->pluck('total', 'month');
            } catch (\Throwable $e) {
                Log::warning("Dashboard perMonth query failed for {$column}: " . $e->getMessage());
                return collect();
            }
        };

        // Monthly trends
        $submitted = $perMonth(Application::query(), 'submitted_at');
        if ($submitted->isEmpty()) {
            $submitted = $perMonth(Application::query(), 'created_at');
        }

        $newSubmitted = $perMonth(Application::query()->where(function ($q) {
            $q->where('type', 'NEW')->orWhereNull('type');
        }), 'submitted_at');
        if ($newSubmitted->isEmpty()) {
            $newSubmitted = $perMonth(Application::query()->where(function ($q) {
                $q->where('type', 'NEW')->orWhereNull('type');
            }), 'created_at');
        }

        $renewalSubmitted = $perMonth(Application::query()->where('type', 'RENEWAL'), 'submitted_at');
        if ($renewalSubmitted->isEmpty()) {
            $renewalSubmitted = $perMonth(Application::query()->where('type', 'RENEWAL'), 'created_at');
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

        // Cards produced calculations (verified confirmed print jobs with outcome PRINTED)
        $producedCardsBase = ResidenceCard::query()->whereHas('printJobs', fn ($q) => $q->where('outcome', \App\Models\CardPrintJob::PRINTED));
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

        // Total applications by biometric enrollment State
        $applicationsByState = (function () {
            try {
                return Application::query()
                    ->leftJoin('enrollment_centers as ec_actual', 'applications.actual_enrollment_center_id', '=', 'ec_actual.id')
                    ->leftJoin('enrollment_centers as ec_selected', 'applications.enrollment_center_id', '=', 'ec_selected.id')
                    ->selectRaw("
                        CASE
                            WHEN applications.actual_enrollment_state IS NOT NULL AND TRIM(applications.actual_enrollment_state) != ''
                                THEN TRIM(applications.actual_enrollment_state)
                            WHEN ec_actual.state IS NOT NULL AND TRIM(ec_actual.state) != ''
                                THEN TRIM(ec_actual.state)
                            WHEN ec_selected.state IS NOT NULL AND TRIM(ec_selected.state) != ''
                                THEN TRIM(ec_selected.state)
                            ELSE 'Not Yet Assigned'
                        END as state_name,
                        count(applications.id) as total
                    ")
                    ->groupByRaw('1')
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn ($r) => [
                        'state' => (string) $r->state_name,
                        'total' => (int) $r->total,
                    ])
                    ->values();
            } catch (\Throwable $e) {
                Log::warning('Dashboard applications by state query failed: ' . $e->getMessage());
                return collect();
            }
        })();

        // Top 5 States by applications (excluding 'Not Yet Assigned')
        $top5States = $applicationsByState
            ->reject(fn ($item) => strtolower($item['state']) === 'not yet assigned' || $item['state'] === '—')
            ->sortByDesc('total')
            ->values()
            ->take(5)
            ->map(function ($item, $idx) {
                return [
                    'rank' => $idx + 1,
                    'state' => $item['state'],
                    'total' => $item['total'],
                ];
            })
            ->values();

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
                        ->orderByDesc('total')
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
                        ->orderByDesc('total')
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
        ];
    }
}

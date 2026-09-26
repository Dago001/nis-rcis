<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Payment;
use App\Models\ResidenceCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'unread_notifications' => $user->unreadNotifications()->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $applications = Application::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $cards = ResidenceCard::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'queue' => collect(ApplicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($applications[$s->value] ?? 0)]),
            'cards' => collect(CardStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($cards[$s->value] ?? 0)]),
            'cards_expired' => ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->where('expires_on', '<', today())->count(),
            'cards_expiring_30_days' => ResidenceCard::whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->whereBetween('expires_on', [today(), today()->addDays(30)])->count(),
            'watchlisted' => ResidenceCard::where('is_watchlisted', true)->count(),
            'paid_awaiting_submission' => Payment::where('status', 'SUCCESS')->whereNotNull('verified_at')->whereNull('application_id')->count(),
            'todays_appointments' => Application::where('status', ApplicationStatus::ApprovedForBiometrics)->whereDate('appointment_date', today())->count(),
            'analytics' => $this->analytics(),
            'my_activity' => [
                'decided' => Application::where('decided_by', $user->id)->count(),
                'captured' => Application::where('biometrics_captured_by', $user->id)->count(),
                'cards_approved' => ResidenceCard::where('approved_by', $user->id)->count(),
            ],
        ]);
    }

    /**
     * Aggregates for the dashboard charts. Counts and sums only: no personal
     * data leaves this method.
     */
    private function analytics(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn ($i) => $start->addMonths($i)->format('Y-m'));

        $perMonth = fn ($query, string $column, string $aggregate = 'count(*)') => $query
            ->where($column, '>=', $start)
            ->selectRaw("to_char(date_trunc('month', {$column}), 'YYYY-MM') as month, {$aggregate} as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $submitted = $perMonth(Application::query(), 'submitted_at');
        $issued = $perMonth(ResidenceCard::query()->whereNotNull('issued_on'), 'issued_on');
        $revenue = $perMonth(Payment::query()->where('status', 'SUCCESS')->whereNotNull('verified_at'), 'paid_at', 'sum(amount_kobo)');

        $decisions = ApplicationStatusHistory::query()
            ->whereIn('to_status', [ApplicationStatus::ApprovedForBiometrics->value, ApplicationStatus::Queried->value, ApplicationStatus::Rejected->value])
            ->selectRaw('to_status, count(*) as total')->groupBy('to_status')->pluck('total', 'to_status');
        $approved = (int) ($decisions[ApplicationStatus::ApprovedForBiometrics->value] ?? 0);
        $queried = (int) ($decisions[ApplicationStatus::Queried->value] ?? 0);
        $rejected = (int) ($decisions[ApplicationStatus::Rejected->value] ?? 0);

        $avgDays = fn (string $from, string $to) => round((float) Application::query()
            ->whereNotNull($from)->whereNotNull($to)
            ->selectRaw("avg(extract(epoch from ({$to} - {$from})) / 86400) as days")
            ->value('days'), 1);

        $split = fn (string $column) => Application::query()
            ->selectRaw("{$column} as label, count(*) as total")->groupBy($column)->orderByDesc('total')
            ->pluck('total', 'label')->map(fn ($v) => (int) $v);

        $paid = Payment::query()->where('status', 'SUCCESS')->whereNotNull('verified_at');

        $appointments = Application::query()
            ->whereIn('status', [ApplicationStatus::PendingApproval, ApplicationStatus::ApprovedForBiometrics])
            ->whereBetween('appointment_date', [today(), today()->addDays(20)])
            ->selectRaw("to_char(appointment_date, 'YYYY-MM-DD') as day, count(*) as total")
            ->groupBy('day')->pluck('total', 'day');
        $days = collect(range(0, 20))->map(fn ($i) => CarbonImmutable::today()->addDays($i))
            ->reject(fn ($d) => $d->isWeekend())->take(10)->values();

        return [
            'monthly' => $months->map(fn ($m) => [
                'month' => $m,
                'submitted' => (int) ($submitted[$m] ?? 0),
                'issued' => (int) ($issued[$m] ?? 0),
                'revenue_naira' => (int) ($revenue[$m] ?? 0) / 100,
            ])->values(),
            'totals' => [
                'applications' => Application::count(),
                'applications_this_month' => (int) ($submitted[$months->last()] ?? 0),
                'applications_last_month' => (int) ($submitted[$months[10]] ?? 0),
                'cards_issued' => ResidenceCard::whereNotNull('issued_on')->count(),
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
            'nationalities' => Application::query()->selectRaw('nationality, count(*) as total')
                ->groupBy('nationality')->orderByDesc('total')->orderBy('nationality')->limit(8)->get()
                ->map(fn ($r) => ['label' => $r->nationality, 'total' => (int) $r->total]),
            'mix' => [
                'type' => $split('type'),
                'channel' => $split('channel'),
                'sex' => $split('sex'),
            ],
            'appointments' => $days->map(fn ($d) => ['date' => $d->toDateString(), 'total' => (int) ($appointments[$d->toDateString()] ?? 0)]),
        ];
    }
}

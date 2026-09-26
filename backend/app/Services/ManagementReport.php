<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CardPrintJob;
use App\Models\CardRenewal;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\Refund;
use App\Models\ResidenceCard;
use App\Support\WorkingDays;
use Carbon\CarbonImmutable;

/**
 * Management report for a period (normally a month): applications, service
 * levels, cards, fees, card production and the centre queue.
 */
class ManagementReport
{
    public function __construct(private readonly CardProduction $production) {}

    /**
     * @return array{title: string, from: string, to: string, generated_at: string, sections: list<array{title: string, rows: list<array{0: string, 1: string|int|float}>}>, tables: list<array{title: string, headings: list<string>, rows: list<list<string|int|float>>}>}
     */
    public function build(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        $between = [$start, $end];
        $target = (int) config('nis.sla_working_days');

        $received = Application::whereBetween('submitted_at', $between);
        $transitions = ApplicationStatusHistory::whereBetween('created_at', $between)
            ->selectRaw('to_status, count(*) as total')->groupBy('to_status')->pluck('total', 'to_status');

        $decided = Application::whereBetween('decided_at', $between)->whereNotNull('submitted_at')->get(['submitted_at', 'decided_at']);
        $days = $decided->map(fn ($a) => WorkingDays::between($a->submitted_at, $a->decided_at));
        $withinTarget = $days->filter(fn ($d) => $d <= $target)->count();

        $payments = Payment::where('status', '!=', 'PENDING')->whereNotNull('verified_at')->whereBetween('paid_at', $between);
        $refunds = Refund::where('status', 'PROCESSED')->whereBetween('processed_at', $between);
        $jobs = CardPrintJob::whereBetween('created_at', $between)->selectRaw('outcome, count(*) as total')->groupBy('outcome')->pluck('total', 'outcome');
        $tickets = QueueTicket::whereBetween('service_date', [$start->toDateString(), $end->toDateString()]);
        $avgWait = (clone $tickets)->whereNotNull('called_at')->get(['checked_in_at', 'called_at'])
            ->avg(fn ($t) => $t->checked_in_at->diffInMinutes($t->called_at));
        $stock = $this->production->stock();

        $naira = fn (int|float $kobo) => 'NGN '.number_format($kobo / 100, 2);

        return [
            'title' => 'Residence Card Management Report',
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'sections' => [
                ['title' => 'Applications', 'rows' => [
                    ['Applications received', (clone $received)->count()],
                    ['  of which online', (clone $received)->where('channel', 'ONLINE')->count()],
                    ['  of which assisted (walk-in)', (clone $received)->where('channel', 'ASSISTED')->count()],
                    ['  of which renewals', (clone $received)->where('type', 'RENEWAL')->count()],
                    ['  of which replacements (lost/stolen)', (clone $received)->where('type', 'REPLACE')->count()],
                    ['  of which dependants (spouse/child)', (clone $received)->whereNotNull('principal_application_id')->count()],
                    ['Approved for biometrics', (int) ($transitions[ApplicationStatus::ApprovedForBiometrics->value] ?? 0)],
                    ['Queried', (int) ($transitions[ApplicationStatus::Queried->value] ?? 0)],
                    ['Rejected', (int) ($transitions[ApplicationStatus::Rejected->value] ?? 0)],
                    ['Biometrics captured', (int) ($transitions[ApplicationStatus::BiometricsCaptured->value] ?? 0)],
                    ['Cards collected', (int) ($transitions[ApplicationStatus::Issued->value] ?? 0)],
                ]],
                ['title' => "Service level (decision within {$target} working days)", 'rows' => [
                    ['Decisions made', $decided->count()],
                    ['Within target', $withinTarget],
                    ['Within target (%)', $decided->count() ? round($withinTarget / $decided->count() * 100, 1).'%' : '—'],
                    ['Average working days to decision', $days->count() ? round($days->avg(), 1) : '—'],
                    ['Waiting now beyond target', Application::where('status', ApplicationStatus::PendingApproval)->where('submitted_at', '<', WorkingDays::cutoff($target))->count()],
                ]],
                ['title' => 'Residence cards', 'rows' => [
                    ['Cards issued', ResidenceCard::whereBetween('issued_on', [$start->toDateString(), $end->toDateString()])->count()],
                    ['Renewals endorsed', CardRenewal::whereBetween('created_at', $between)->count()],
                    ['Cards revoked', ResidenceCard::whereBetween('revoked_at', $between)->count()],
                    ['Reported lost or stolen (still open)', ResidenceCard::whereBetween('reported_lost_at', $between)->count()],
                ]],
                ['title' => 'Fees', 'rows' => [
                    ['Confirmed payments', (clone $payments)->count()],
                    ['Fees collected', $naira((clone $payments)->sum('amount_kobo'))],
                    ['Refunds paid', (clone $refunds)->count()],
                    ['Amount refunded', $naira((clone $refunds)->sum('amount_kobo'))],
                ]],
                ['title' => 'Card production', 'rows' => [
                    ['Cards printed', (int) ($jobs[CardPrintJob::PRINTED] ?? 0)],
                    ['Cards spoiled', (int) ($jobs[CardPrintJob::SPOILED] ?? 0)],
                    ['Blank cards left in stock (now)', $stock['remaining']],
                ]],
                ['title' => 'Enrollment centre queue', 'rows' => [
                    ['Tickets issued', (clone $tickets)->count()],
                    ['Walk-ins', (clone $tickets)->where('kind', 'WALK_IN')->count()],
                    ['Served', (clone $tickets)->where('status', 'DONE')->count()],
                    ['No-shows', (clone $tickets)->where('status', 'NO_SHOW')->count()],
                    ['Average wait (minutes)', $avgWait !== null ? round($avgWait, 1) : '—'],
                ]],
            ],
            'tables' => [
                [
                    'title' => 'Applications by nationality (top 15)',
                    'headings' => ['Nationality', 'Applications'],
                    'rows' => (clone $received)->selectRaw('nationality, count(*) as total')->groupBy('nationality')->orderByDesc('total')->limit(15)
                        ->get()->map(fn ($r) => [$r->nationality, (int) $r->total])->all(),
                ],
                [
                    'title' => 'Decisions by officer',
                    'headings' => ['Officer', 'Service no.', 'Decisions'],
                    'rows' => Application::whereBetween('decided_at', $between)->whereNotNull('decided_by')
                        ->join('users', 'users.id', '=', 'applications.decided_by')
                        ->selectRaw('users.fullname, users.service_number, count(*) as total')
                        ->groupBy('users.fullname', 'users.service_number')->orderByDesc('total')
                        ->get()->map(fn ($r) => [$r->fullname, $r->service_number, (int) $r->total])->all(),
                ],
            ],
        ];
    }
}

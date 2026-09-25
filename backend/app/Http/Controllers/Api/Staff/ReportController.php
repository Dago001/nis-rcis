<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\CardStatus;
use App\Models\CardRenewal;
use App\Models\ResidenceCard;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Issuance statistics (legacy reports.php) and CSV export (legacy export.php).
 */
class ReportController
{
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $cards = ResidenceCard::whereBetween('issued_on', [$from, $to]);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total_issued' => (clone $cards)->count(),
            'active' => (clone $cards)->whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->count(),
            'revoked' => (clone $cards)->where('status', CardStatus::Revoked)->count(),
            'renewals' => CardRenewal::whereBetween('created_at', [$from, "{$to} 23:59:59"])->count(),
            'nationalities' => (clone $cards)->distinct()->count('nationality'),
            'by_nationality' => (clone $cards)->selectRaw('nationality, count(*) as total')->groupBy('nationality')->orderByDesc('total')->get(),
            'by_month' => (clone $cards)->selectRaw("to_char(issued_on, 'YYYY-MM') as month, count(*) as total")->groupBy('month')->orderBy('month')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        Audit::log('CARDS_EXPORTED', "Card register exported ({$from} to {$to})", context: compact('from', 'to'));

        $columns = ['card_number', 'booklet_number', 'surname', 'forenames', 'nationality', 'sex', 'date_of_birth',
            'passport_number', 'profession', 'issued_on', 'issued_at', 'expires_on', 'status', 'is_watchlisted'];

        return response()->streamDownload(function () use ($from, $to, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            ResidenceCard::whereBetween('issued_on', [$from, $to])->orderBy('id')
                ->chunk(500, function ($cards) use ($out, $columns) {
                    foreach ($cards as $card) {
                        fputcsv($out, array_map(fn ($c) => $this->csvSafe($card->{$c}), $columns));
                    }
                });
            fclose($out);
        }, "nis-rcis-cards-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $data['from'] ?? now()->startOfYear()->toDateString(),
            $data['to'] ?? now()->toDateString(),
        ];
    }

    /** Prevent CSV formula injection when opened in Excel. */
    private function csvSafe(mixed $value): string
    {
        $value = match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? 'YES' : 'NO',
            default => (string) $value,
        };

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}

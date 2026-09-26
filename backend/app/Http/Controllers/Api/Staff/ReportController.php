<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\CardStatus;
use App\Models\CardRenewal;
use App\Models\ResidenceCard;
use App\Models\SavedReportFilter;
use App\Services\ManagementReport;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Issuance statistics (legacy reports.php), card register export (legacy
 * export.php), management reports and saved report filters.
 */
class ReportController
{
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CardStatus::class)],
            'nationality' => ['nullable', 'string', 'max:100'],
        ]);
        $cards = ResidenceCard::whereBetween('issued_on', [$from, $to])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['nationality'] ?? null, fn ($q, $n) => $q->where('nationality', mb_strtoupper($n)));

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

    /**
     * Card register download, CSV (legacy export.php) or Excel, with the
     * same filters as the reports page.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $filters = $request->validate([
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'status' => ['nullable', Rule::enum(CardStatus::class)],
            'nationality' => ['nullable', 'string', 'max:100'],
        ]);
        $format = $filters['format'] ?? 'csv';
        Audit::log('CARDS_EXPORTED', "Card register exported ({$from} to {$to}, {$format})", context: compact('from', 'to') + $filters);

        $columns = ['card_number', 'booklet_number', 'surname', 'forenames', 'nationality', 'sex', 'date_of_birth',
            'passport_number', 'profession', 'issued_on', 'issued_at', 'expires_on', 'status', 'is_watchlisted'];
        $query = ResidenceCard::whereBetween('issued_on', [$from, $to])->orderBy('id')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['nationality'] ?? null, fn ($q, $n) => $q->where('nationality', mb_strtoupper($n)));
        $name = "nis-rcis-cards-{$from}-to-{$to}";

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($query, $columns) {
                $writer = new XlsxWriter;
                $writer->openToFile('php://output');
                $writer->getCurrentSheet()->setName('Card register');
                $writer->addRow(Row::fromValuesWithStyle(array_map(fn ($c) => strtoupper(str_replace('_', ' ', $c)), $columns), new Style(fontBold: true, backgroundColor: 'EEF7F1')));
                $query->chunk(500, function ($cards) use ($writer, $columns) {
                    foreach ($cards as $card) {
                        $writer->addRow(Row::fromValues(array_map(fn ($c) => $this->csvSafe($card->{$c}), $columns)));
                    }
                });
                $writer->close();
            }, "{$name}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            $query->chunk(500, function ($cards) use ($out, $columns) {
                foreach ($cards as $card) {
                    fputcsv($out, array_map(fn ($c) => $this->csvSafe($card->{$c}), $columns));
                }
            });
            fclose($out);
        }, "{$name}.csv", ['Content-Type' => 'text/csv']);
    }

    /** Monthly (or any period) management report as PDF or Excel. */
    public function management(Request $request, ManagementReport $reports): Response
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', Rule::in(['pdf', 'xlsx'])],
        ]);
        if (! empty($data['from'])) {
            [$from, $to] = [$data['from'], $data['to'] ?? now()->toDateString()];
        } else {
            $month = CarbonImmutable::createFromFormat('Y-m-d', ($data['month'] ?? now()->format('Y-m')).'-01');
            [$from, $to] = [$month->toDateString(), $month->endOfMonth()->toDateString()];
        }
        $report = $reports->build($from, $to);
        $format = $data['format'] ?? 'pdf';
        $user = $request->user();
        Audit::log('MANAGEMENT_REPORT', "Management report {$from} to {$to} ({$format})", context: compact('from', 'to', 'format'));
        $name = "nis-rcis-management-report-{$from}-to-{$to}";

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($report) {
                $bold = new Style(fontBold: true);
                $head = new Style(fontBold: true, backgroundColor: 'EEF7F1');
                $writer = new XlsxWriter;
                $writer->openToFile('php://output');
                $writer->getCurrentSheet()->setName('Summary');
                $writer->addRow(Row::fromValuesWithStyle([$report['title']], new Style(fontBold: true, fontSize: 14)));
                $writer->addRow(Row::fromValues(["{$report['from']} to {$report['to']}"]));
                foreach ($report['sections'] as $section) {
                    $writer->addRow(Row::fromValues([]));
                    $writer->addRow(Row::fromValuesWithStyle([$section['title'], ''], $head));
                    foreach ($section['rows'] as [$label, $value]) {
                        $writer->addRow(Row::fromValues([trim($label), $value]));
                    }
                }
                foreach ($report['tables'] as $table) {
                    $writer->addNewSheetAndMakeItCurrent()->setName(mb_substr(preg_replace('/[^\pL0-9 ]/u', '', $table['title']), 0, 31));
                    $writer->addRow(Row::fromValuesWithStyle($table['headings'], $bold));
                    foreach ($table['rows'] as $row) {
                        $writer->addRow(Row::fromValues($row));
                    }
                }
                $writer->close();
            }, "{$name}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        $options = new Options;
        $options->setChroot(public_path());
        $options->setIsRemoteEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.management-report', [
            'report' => $report, 'logo' => public_path('images/nis-logo.png'), 'by' => "{$user->fullname} ({$user->service_number})",
        ])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$name}.pdf\"",
        ]);
    }

    /** Report filters saved by the signed-in officer. */
    public function filters(Request $request): JsonResponse
    {
        return response()->json(['data' => SavedReportFilter::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'filters'])]);
    }

    public function saveFilter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['required', 'array'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date'],
            'filters.status' => ['nullable', Rule::enum(CardStatus::class)],
            'filters.nationality' => ['nullable', 'string', 'max:100'],
        ]);
        $filter = SavedReportFilter::updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            ['filters' => Arr::only($data['filters'], ['from', 'to', 'status', 'nationality'])],
        );

        return response()->json(['data' => $filter->only(['id', 'name', 'filters'])], 201);
    }

    public function deleteFilter(Request $request, int $id): Response
    {
        SavedReportFilter::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->noContent();
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

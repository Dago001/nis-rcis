<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\CardStatus;
use App\Models\CardRenewal;
use App\Models\ResidenceCard;
use App\Models\SavedReportFilter;
use App\Services\ManagementReport;
use App\Support\Audit;
use App\Support\Like;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
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
    /**
     * Paginated list of confirmed issued residence cards matching active filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->input('per_page', 10));
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 10;
        }

        $query = $this->baseQuery($request)->orderByDesc('issued_on')->orderByDesc('id');
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function (ResidenceCard $card) {
            $app = $card->application;
            $isRenewal = $card->status === CardStatus::Renewed || $app?->type === 'RENEWAL' || $card->renewals()->exists();
            $isReplacement = $app?->type === 'REPLACE';
            $cardCategory = $isReplacement ? 'Replacement' : ($isRenewal ? 'Renewal' : 'New');

            return [
                'id' => $card->id,
                'card_number' => $card->card_number,
                'application_number' => $app?->application_number ?? ($card->decision_reference ?: '—'),
                'reference_number' => $app?->reference_number ?? '—',
                'applicant_name' => trim("{$card->surname}, {$card->forenames}"),
                'forenames' => $card->forenames,
                'surname' => $card->surname,
                'passport_number' => $card->passport_number,
                'nationality' => $card->nationality,
                'application_category' => $app?->category ?? 'Standard',
                'card_category' => $cardCategory,
                'issuance_status' => $card->status->value,
                'issuance_status_label' => $card->status === CardStatus::Issued ? 'Issued' : ($card->status === CardStatus::Renewed ? 'Renewed' : $card->status->value),
                'issued_on' => $card->issued_on?->toDateString() ?? '—',
                'expires_on' => $card->expires_on?->toDateString() ?? '—',
                'issuing_officer' => $card->issuing_officer_name ?: ($card->issuingOfficer?->fullname ?? 'NIS Issuing Officer'),
                'issuance_location' => $card->issued_at ?: ($app?->enrollmentCenter?->name ?? 'NIS Headquarters, Abuja'),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
        ]);
    }

    /**
     * Issuance summary and statistics calculated strictly from actual issued-card records.
     */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $cards = $this->baseQuery($request);

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $monthFormat = match ($driver) {
            'mysql' => "DATE_FORMAT(issued_on, '%Y-%m')",
            'sqlite' => "strftime('%Y-%m', issued_on)",
            default => "to_char(issued_on, 'YYYY-MM')",
        };

        $totalIssued = (clone $cards)->count();

        $newIssued = (clone $cards)->where(function ($q) {
            $q->whereHas('application', fn ($sq) => $sq->where('type', 'NEW')->orWhereNull('type'))
              ->orWhereDoesntHave('application');
        })->where('status', CardStatus::Issued)->whereDoesntHave('renewals')->count();

        $renewedIssued = (clone $cards)->where(function ($q) {
            $q->where('status', CardStatus::Renewed)
              ->orWhereHas('application', fn ($sq) => $sq->where('type', 'RENEWAL'))
              ->orWhereHas('renewals');
        })->count();

        $replacementIssued = (clone $cards)->whereHas('application', fn ($sq) => $sq->where('type', 'REPLACE'))->count();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total_issued' => $totalIssued,
            'new_issued' => $newIssued,
            'renewed_issued' => $renewedIssued,
            'replacement_issued' => $replacementIssued,
            // Backwards-compatible keys
            'active' => (clone $cards)->whereIn('status', [CardStatus::Issued, CardStatus::Renewed])->count(),
            'revoked' => (clone $cards)->where('status', CardStatus::Revoked)->count(),
            'renewals' => $renewedIssued,
            'nationalities' => (clone $cards)->distinct()->count('nationality'),
            'by_nationality' => (clone $cards)->selectRaw('nationality, count(*) as total')->groupBy('nationality')->orderByDesc('total')->get(),
            'by_month' => (clone $cards)->selectRaw("{$monthFormat} as month, count(*) as total")->groupBy('month')->orderBy('month')->get(),
        ]);
    }

    /**
     * Card register export: CSV, Excel (XLSX), or PDF with full filter support.
     */
    public function export(Request $request): Response|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $format = $request->input('format', 'csv');
        $query = $this->baseQuery($request)->orderByDesc('issued_on')->orderByDesc('id');

        Audit::log('CARDS_EXPORTED', "Issuance report exported ({$from} to {$to}, {$format})", context: compact('from', 'to', 'format'));

        $name = "nis-rcis-issuance-report-{$from}-to-{$to}";

        $headers = [
            'S/N', 'Card Number', 'Application Number', 'Reference Number', 'Applicant Name',
            'Passport Number', 'Nationality', 'Application Category', 'Card Category',
            'Issuance Status', 'Date of Issuance', 'Expiry Date', 'Issuing Officer', 'Issuance Location'
        ];

        $mapCard = function (ResidenceCard $card, int $index) {
            $app = $card->application;
            $isRenewal = $card->status === CardStatus::Renewed || $app?->type === 'RENEWAL' || $card->renewals()->exists();
            $isReplacement = $app?->type === 'REPLACE';
            $cardCategory = $isReplacement ? 'Replacement' : ($isRenewal ? 'Renewal' : 'New');

            return [
                $index,
                $this->csvSafe($card->card_number),
                $this->csvSafe($app?->application_number ?? ($card->decision_reference ?: '—')),
                $this->csvSafe($app?->reference_number ?? '—'),
                $this->csvSafe(trim("{$card->surname}, {$card->forenames}")),
                $this->csvSafe($card->passport_number),
                $this->csvSafe($card->nationality),
                $this->csvSafe($app?->category ?? 'Standard'),
                $this->csvSafe($cardCategory),
                $this->csvSafe($card->status->value),
                $this->csvSafe($card->issued_on?->toDateString() ?? '—'),
                $this->csvSafe($card->expires_on?->toDateString() ?? '—'),
                $this->csvSafe($card->issuing_officer_name ?: ($card->issuingOfficer?->fullname ?? 'NIS Issuing Officer')),
                $this->csvSafe($card->issued_at ?: ($app?->enrollmentCenter?->name ?? 'NIS Headquarters, Abuja')),
            ];
        };

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($query, $headers, $mapCard) {
                $writer = new XlsxWriter;
                $writer->openToFile('php://output');
                $writer->getCurrentSheet()->setName('Issuance Report');
                $writer->addRow(Row::fromValuesWithStyle($headers, new Style(fontBold: true, backgroundColor: 'EEF7F1')));
                $sn = 1;
                $query->chunk(500, function ($cards) use ($writer, $mapCard, &$sn) {
                    foreach ($cards as $card) {
                        $writer->addRow(Row::fromValues($mapCard($card, $sn++)));
                    }
                });
                $writer->close();
            }, "{$name}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        if ($format === 'pdf') {
            $cards = $query->limit(500)->get();
            $sn = 1;
            $rows = $cards->map(fn ($c) => $mapCard($c, $sn++))->all();
            $options = new Options;
            $options->setChroot(public_path());
            $options->setIsRemoteEnabled(false);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('pdf.issuance-report', [
                'from' => $from,
                'to' => $to,
                'total' => $cards->count(),
                'headers' => $headers,
                'rows' => $rows,
                'logo' => public_path('images/nis-logo.png'),
                'by' => $request->user() ? "{$request->user()->fullname} ({$request->user()->service_number})" : 'System Report',
            ])->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$name}.pdf\"",
            ]);
        }

        return response()->streamDownload(function () use ($query, $headers, $mapCard) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            $sn = 1;
            $query->chunk(500, function ($cards) use ($out, $mapCard, &$sn) {
                foreach ($cards as $card) {
                    fputcsv($out, $mapCard($card, $sn++));
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
            'filters.category' => ['nullable', 'string', 'max:100'],
            'filters.card_category' => ['nullable', 'string', 'max:50'],
            'filters.location' => ['nullable', 'string', 'max:100'],
        ]);
        $filter = SavedReportFilter::updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            ['filters' => Arr::only($data['filters'], ['from', 'to', 'status', 'nationality', 'category', 'card_category', 'location'])],
        );

        return response()->json(['data' => $filter->only(['id', 'name', 'filters'])], 201);
    }

    public function deleteFilter(Request $request, int $id): Response
    {
        SavedReportFilter::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->noContent();
    }

    /**
     * Build base query strictly for confirmed issued residence cards matching the filters.
     * Excludes cards that are merely approved, awaiting biometrics, awaiting production or printed but unissued.
     */
    private function baseQuery(Request $request): Builder
    {
        [$from, $to] = $this->range($request);
        $status = $request->input('status');
        $nationality = $request->input('nationality');
        $category = $request->input('category');
        $cardCategory = $request->input('card_category');
        $location = $request->input('location');
        $search = $request->input('search');

        $query = ResidenceCard::query()
            ->with([
                'application.enrollmentCenter',
                'issuingOfficer:id,fullname,service_number',
            ])
            ->whereBetween('issued_on', [$from, $to]);

        // Strictly confirmed issued cards by default
        if (! empty($status)) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [CardStatus::Issued, CardStatus::Renewed]);
        }

        if (! empty($nationality)) {
            $query->where('nationality', mb_strtoupper(trim($nationality)));
        }

        if (! empty($category)) {
            $query->whereHas('application', fn ($q) => $q->where('category', $category));
        }

        if (! empty($cardCategory)) {
            $cardCatUpper = strtoupper(trim($cardCategory));
            if ($cardCatUpper === 'NEW') {
                $query->where(function ($q) {
                    $q->whereHas('application', fn ($sq) => $sq->where('type', 'NEW')->orWhereNull('type'))
                      ->orWhereDoesntHave('application');
                })->where('status', CardStatus::Issued)->whereDoesntHave('renewals');
            } elseif ($cardCatUpper === 'RENEWAL' || $cardCatUpper === 'RENEWED') {
                $query->where(function ($q) {
                    $q->where('status', CardStatus::Renewed)
                      ->orWhereHas('application', fn ($sq) => $sq->where('type', 'RENEWAL'))
                      ->orWhereHas('renewals');
                });
            } elseif ($cardCatUpper === 'REPLACE' || $cardCatUpper === 'REPLACEMENT') {
                $query->whereHas('application', fn ($sq) => $sq->where('type', 'REPLACE'));
            }
        }

        if (! empty($location)) {
            $loc = trim($location);
            $query->where(function ($q) use ($loc) {
                $q->where('issued_at', 'like', "%{$loc}%")
                  ->orWhereHas('application.enrollmentCenter', fn ($sq) => $sq->where('name', 'like', "%{$loc}%")->orWhere('code', $loc));
            });
        }

        if (! empty($search)) {
            $term = trim($search);
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';
            $pattern = Like::contains($term);
            $cleanNum = preg_replace('/[^A-Z0-9]/', '', strtoupper($term));
            $concatSurnameForenames = $driver === 'sqlite' ? "(surname || ' ' || forenames)" : "CONCAT(surname, ' ', forenames)";
            $concatForenamesSurname = $driver === 'sqlite' ? "(forenames || ' ' || surname)" : "CONCAT(forenames, ' ', surname)";

            $query->where(function ($q) use ($term, $cleanNum, $likeOp, $pattern, $concatSurnameForenames, $concatForenamesSurname) {
                $q->where('card_number', $likeOp, $pattern)
                    ->orWhere('booklet_number', $likeOp, $pattern)
                    ->orWhere('passport_number', $likeOp, $pattern)
                    ->orWhere('surname', $likeOp, $pattern)
                    ->orWhere('forenames', $likeOp, $pattern)
                    ->orWhereRaw("{$concatSurnameForenames} {$likeOp} ?", [$pattern])
                    ->orWhereRaw("{$concatForenamesSurname} {$likeOp} ?", [$pattern])
                    ->orWhereHas('application', fn ($sq) => $sq->where('application_number', $likeOp, $pattern)
                        ->orWhere('reference_number', $likeOp, $pattern));
                if ($cleanNum !== '') {
                    $q->orWhere('card_number', $likeOp, "%{$cleanNum}%")
                      ->orWhere('passport_number', $likeOp, "%{$cleanNum}%");
                }
            });
        }

        return $query;
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

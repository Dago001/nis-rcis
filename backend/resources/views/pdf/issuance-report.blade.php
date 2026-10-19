<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>ECOWAS Residence Card Issuance Report</title>
<style>
    @page { margin: 15mm 12mm 15mm; size: A4 landscape; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1e293b; line-height: 1.3; }
    .head { text-align: center; border-bottom: 2px solid #2b892b; padding-bottom: 8px; margin-bottom: 12px; }
    .head img { height: 48px; }
    .org { font-size: 13px; font-weight: bold; color: #1f6b1f; margin-top: 2px; }
    .subhead { font-size: 10px; color: #334155; }
    h1 { font-size: 15px; text-align: center; margin: 8px 0 2px; color: #0f172a; }
    .period { text-align: center; font-size: 10px; color: #475569; margin-bottom: 12px; }
    .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #cbd5e1; }
    .stats-table td { padding: 6px 10px; font-size: 9px; }
    .stats-table .lbl { color: #475569; text-transform: uppercase; font-size: 8px; font-weight: bold; }
    .stats-table .val { font-size: 12px; font-weight: bold; color: #1f6b1f; }
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.data-table th, table.data-table td { padding: 4.5px 6px; border: 1px solid #cbd5e1; text-align: left; }
    table.data-table th { background: #eef7f1; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; color: #1f6b1f; font-weight: bold; }
    table.data-table tr:nth-child(even) { background: #f8fafc; }
    .mono { font-family: monospace; font-weight: bold; }
    .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 7.5px; font-weight: bold; background: #e2e8f0; color: #334155; }
    .badge-issued { background: #dcfce7; color: #166534; }
    .badge-renewed { background: #e0f2fe; color: #0369a1; }
    .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #64748b; text-align: center; }
</style>
</head>
<body>
    <div class="foot">
        Nigeria Immigration Service · ECOWAS Residence Card Issuance Register · Generated {{ now()->format('d/m/Y H:i') }} by {{ $by }} · CONFIDENTIAL / OFFICIAL USE ONLY
    </div>

    <div class="head">
        @if(file_exists($logo))
            <img src="{{ $logo }}" alt="NIS Logo">
        @endif
        <div class="org">NIGERIA IMMIGRATION SERVICE</div>
        <div class="subhead">Directorate of Visa and Residency · ECOWAS Residence Card Portal</div>
    </div>

    <h1>Official Residence Card Issuance Report</h1>
    <div class="period">Reporting Period: <strong>{{ \Carbon\Carbon::parse($from)->format('d F Y') }}</strong> to <strong>{{ \Carbon\Carbon::parse($to)->format('d F Y') }}</strong> · Total Records: <strong>{{ number_format($total) }}</strong></div>

    <table class="data-table">
        <thead>
            <tr>
                @foreach ($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $idx => $cell)
                        @if ($idx === 0)
                            <td style="text-align: center; color: #64748b; font-size: 8px;">{{ $cell }}</td>
                        @elseif ($idx === 1)
                            <td class="mono" style="color: #166534;">{{ $cell }}</td>
                        @elseif ($idx === 9)
                            <td><span class="badge badge-issued">{{ $cell }}</span></td>
                        @else
                            <td>{{ $cell }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align: center; padding: 16px; color: #64748b;">
                        No confirmed issued residence cards found for the selected reporting criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

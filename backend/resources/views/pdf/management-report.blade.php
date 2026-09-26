<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $report['title'] }}</title>
<style>
    @page { margin: 18mm 16mm 20mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
    .head { text-align: center; border-bottom: 2px solid #2b892b; padding-bottom: 8px; }
    .head img { height: 52px; }
    .org { font-size: 13px; font-weight: bold; color: #1f6b1f; margin-top: 3px; }
    h1 { font-size: 16px; text-align: center; margin: 12px 0 2px; }
    .period { text-align: center; color: #475569; margin-bottom: 12px; }
    h2 { font-size: 11.5px; color: #1f6b1f; background: #eef7f1; padding: 5px 8px; margin: 14px 0 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    th { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #475569; }
    td.v { text-align: right; width: 30%; font-weight: bold; }
    td.sub { padding-left: 20px; color: #475569; }
    .foot { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #64748b; text-align: center; }
</style>
</head>
<body>
    <div class="foot">Nigeria Immigration Service · Residence Card Issuance System · generated {{ \Carbon\Carbon::parse($report['generated_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }} by {{ $by }} · RESTRICTED</div>
    <div class="head">
        <img src="{{ $logo }}" alt="">
        <div class="org">NIGERIA IMMIGRATION SERVICE</div>
        <div>Directorate of Visa and Residency</div>
    </div>
    <h1>{{ $report['title'] }}</h1>
    <div class="period">{{ \Carbon\Carbon::parse($report['from'])->format('j F Y') }} to {{ \Carbon\Carbon::parse($report['to'])->format('j F Y') }}</div>

    @foreach ($report['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        <table>
            @foreach ($section['rows'] as [$label, $value])
                <tr><td class="{{ str_starts_with($label, '  ') ? 'sub' : '' }}">{{ trim($label) }}</td><td class="v">{{ is_int($value) ? number_format($value) : $value }}</td></tr>
            @endforeach
        </table>
    @endforeach

    @foreach ($report['tables'] as $table)
        <h2>{{ $table['title'] }}</h2>
        <table>
            <tr>@foreach ($table['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr>
            @forelse ($table['rows'] as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($table['headings']) }}">None in this period.</td></tr>
            @endforelse
        </table>
    @endforeach
</body>
</html>

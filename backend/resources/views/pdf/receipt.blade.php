<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payment receipt {{ $payment->reference }}</title>
<style>
    @page { margin: 28mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .head { text-align: center; border-bottom: 2px solid #2b892b; padding-bottom: 10px; }
    .head img { height: 60px; }
    .org { font-size: 14px; font-weight: bold; color: #1f6b1f; margin-top: 4px; }
    h1 { font-size: 18px; text-align: center; margin: 16px 0 4px; }
    .paid { text-align: center; margin: 6px 0 18px; }
    .paid span { border: 2px solid #2b892b; color: #2b892b; border-radius: 12px; padding: 3px 14px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
    td.k { width: 42%; color: #64748b; }
    .total td { font-size: 14px; font-weight: bold; border-top: 2px solid #1e293b; }
    .foot { margin-top: 26px; font-size: 9px; color: #64748b; text-align: center; }
</style>
</head>
<body>
    <div class="head">
        <img src="{{ $logo }}" alt="">
        <div class="org">NIGERIA IMMIGRATION SERVICE</div>
        <div>Directorate of Visa and Residency · Residence Card Issuance System</div>
    </div>
    <h1>Payment receipt</h1>
    <div class="paid"><span>{{ $payment->status === 'REFUNDED' ? 'REFUNDED' : 'PAID' }}</span></div>
    <table>
        <tr><td class="k">Receipt number</td><td>{{ $payment->reference }}</td></tr>
        <tr><td class="k">Received from</td><td>{{ $applicant->surname }}, {{ $applicant->forenames }}</td></tr>
        <tr><td class="k">E-mail</td><td>{{ $applicant->email }}</td></tr>
        <tr><td class="k">Payment for</td><td>Residence card fee{{ $application ? ' – application '.$application->application_number : '' }}</td></tr>
        <tr><td class="k">Payment date</td><td>{{ $payment->paid_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="k">Payment gateway</td><td>{{ $receipt['gateway'] }}</td></tr>
        <tr><td class="k">Payment mode</td><td>{{ $receipt['mode'] ?? '—' }}</td></tr>
        <tr><td class="k">Gateway reference</td><td>{{ $receipt['gateway_reference'] ?? '—' }}</td></tr>
        <tr><td class="k">Card</td><td>{{ $receipt['pan'] ?? '—' }}</td></tr>
        <tr class="total"><td>Amount paid</td><td>NGN {{ number_format($payment->amount_kobo / 100, 2) }}</td></tr>
    </table>
    <p class="foot">This receipt was generated electronically on {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }} and is valid without a signature.<br>Verify at {{ config('nis.frontend_url') }} · Nigeria Immigration Service. All rights reserved.</p>
</body>
</html>

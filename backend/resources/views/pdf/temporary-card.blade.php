<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Temporary Residence Card - {{ $card['temporary_card_number'] }}</title>
<style>
    @page {
        margin: 15mm 15mm 15mm 15mm;
        size: A4 portrait;
    }
    body {
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        color: #1e293b;
        margin: 0;
        padding: 0;
        font-size: 11px;
        line-height: 1.4;
    }
    .watermark-banner {
        background-color: #fffbeb;
        border: 1px dashed #d97706;
        color: #92400e;
        text-align: center;
        padding: 6px 10px;
        margin-bottom: 15px;
        border-radius: 4px;
        font-size: 9.5px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    .card-container {
        border: 2.5px solid #15803d;
        border-radius: 8px;
        padding: 16px 20px;
        background-color: #ffffff;
        position: relative;
    }
    .card-header {
        text-align: center;
        border-bottom: 2px solid #15803d;
        padding-bottom: 12px;
        margin-bottom: 14px;
    }
    .logo {
        height: 58px;
        margin-bottom: 4px;
    }
    .country {
        font-size: 13px;
        font-weight: bold;
        color: #0f172a;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .agency {
        font-size: 15px;
        font-weight: bold;
        color: #15803d;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        margin-top: 2px;
    }
    .directorate {
        font-size: 9px;
        color: #475569;
        margin-top: 2px;
    }
    .doc-title-box {
        background-color: #f0fdf4;
        border: 1px solid #86efac;
        padding: 6px 12px;
        border-radius: 4px;
        margin: 10px auto 14px;
        text-align: center;
    }
    .doc-title {
        font-size: 14px;
        font-weight: bold;
        color: #166534;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .doc-subtitle {
        font-size: 10px;
        font-style: italic;
        color: #15803d;
    }
    .main-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    .main-grid td {
        vertical-align: top;
    }
    .photo-col {
        width: 140px;
        padding-right: 18px;
        text-align: center;
    }
    .photo-box {
        width: 130px;
        height: 160px;
        border: 1.5px solid #cbd5e1;
        border-radius: 4px;
        overflow: hidden;
        background-color: #f8fafc;
        margin: 0 auto;
    }
    .photo-img {
        width: 130px;
        height: 160px;
        object-fit: cover;
    }
    .sig-box {
        width: 130px;
        height: 48px;
        border: 1px dashed #cbd5e1;
        border-radius: 4px;
        margin: 8px auto 0;
        background-color: #f8fafc;
        text-align: center;
    }
    .sig-img {
        max-width: 120px;
        max-height: 44px;
    }
    .sig-label {
        font-size: 8px;
        color: #64748b;
        margin-top: 2px;
        text-transform: uppercase;
    }
    .particulars-col {
        padding-right: 15px;
    }
    .particulars-table {
        width: 100%;
        border-collapse: collapse;
    }
    .particulars-table td {
        padding: 4.5px 6px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 10.5px;
    }
    .particulars-table .lbl {
        width: 36%;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        font-size: 9px;
    }
    .particulars-table .val {
        color: #0f172a;
        font-weight: 600;
    }
    .val-highlight {
        color: #15803d !important;
        font-weight: bold;
    }
    .qr-col {
        width: 135px;
        text-align: center;
        border-left: 1px solid #e2e8f0;
        padding-left: 14px;
    }
    .qr-box {
        width: 120px;
        height: 120px;
        margin: 0 auto;
        padding: 4px;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
    }
    .qr-box img {
        width: 112px;
        height: 112px;
    }
    .qr-desc {
        font-size: 8px;
        color: #64748b;
        margin-top: 6px;
        line-height: 1.2;
    }
    .status-badge {
        display: inline-block;
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: bold;
        font-size: 9px;
        text-transform: uppercase;
        margin-top: 6px;
    }
    .notice-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 14px;
        margin-top: 10px;
        font-size: 9px;
        color: #475569;
        line-height: 1.45;
    }
    .notice-title {
        font-weight: bold;
        color: #1e293b;
        text-transform: uppercase;
        margin-bottom: 3px;
        font-size: 9.5px;
    }
    .footer {
        margin-top: 20px;
        text-align: center;
        font-size: 8.5px;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        padding-top: 10px;
    }
</style>
</head>
<body>

    <div class="watermark-banner">
        ★ {{ $card['disclaimer'] }} ★
    </div>

    <div class="card-container">
        <!-- Header -->
        <div class="card-header">
            @if(!empty($logo))
                <img src="{{ $logo }}" class="logo" alt="Nigeria Immigration Service">
            @endif
            <div class="country">Federal Republic of Nigeria</div>
            <div class="agency">Nigeria Immigration Service</div>
            <div class="directorate">Directorate of Visa and Residency · ECOWAS Residence Card System</div>

            <div class="doc-title-box">
                <div class="doc-title">Temporary Residence Card / Carte de Séjour Temporaire</div>
                <div class="doc-subtitle">Official Interim Proof of Biometrics Completion & Residency</div>
            </div>
        </div>

        <!-- Main Particulars Grid -->
        <table class="main-grid">
            <tr>
                <!-- Applicant Photo & Signature -->
                <td class="photo-col">
                    <div class="photo-box">
                        @if(!empty($photo))
                            <img src="{{ $photo }}" class="photo-img" alt="Applicant Photo">
                        @else
                            <div style="padding-top: 60px; color: #94a3b8; font-size: 10px;">PHOTO<br>CAPTURED</div>
                        @endif
                    </div>
                    @if(!empty($signature))
                        <div class="sig-box">
                            <img src="{{ $signature }}" class="sig-img" alt="Holder Signature">
                        </div>
                        <div class="sig-label">Digital Signature</div>
                    @endif
                </td>

                <!-- Applicant Particulars -->
                <td class="particulars-col">
                    <table class="particulars-table">
                        <tr>
                            <td class="lbl">Document Ref</td>
                            <td class="val val-highlight">{{ $card['temporary_card_number'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Application No</td>
                            <td class="val">{{ $card['application_number'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Reference No</td>
                            <td class="val">{{ $card['reference_number'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Full Name</td>
                            <td class="val" style="font-size: 11.5px; font-weight: bold;">{{ $card['applicant_name'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Nationality</td>
                            <td class="val">{{ $card['nationality'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Passport No</td>
                            <td class="val">{{ $card['passport_number'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Category</td>
                            <td class="val">{{ $card['category'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Date of Birth</td>
                            <td class="val">{{ $card['date_of_birth'] }} (Sex: {{ $card['sex'] }})</td>
                        </tr>
                        <tr>
                            <td class="lbl">Domicile / Address</td>
                            <td class="val">{{ $card['domicile'] }}{{ $card['domicile_state'] ? ', '.$card['domicile_state'] : '' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Enrollment Office</td>
                            <td class="val">{{ $card['enrollment_center'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Date of Capture</td>
                            <td class="val">{{ $card['issue_date'] }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Valid Until</td>
                            <td class="val val-highlight">{{ $card['expiry_date'] }} ({{ $card['validity_period'] }})</td>
                        </tr>
                    </table>
                </td>

                <!-- QR Code & Status -->
                <td class="qr-col">
                    <div class="qr-box">
                        <img src="{{ $card['qr_svg'] }}" alt="Security QR Code">
                    </div>
                    <div class="status-badge">✓ BIOMETRICS COMPLETED</div>
                    <div class="qr-desc">
                        Official verification barcode.<br>
                        Scan by law enforcement or immigration officer to verify authenticity.
                    </div>
                </td>
            </tr>
        </table>

        <!-- Regulatory Notice & Instructions -->
        <div class="notice-box">
            <div class="notice-title">Important Legal & Regulatory Notice:</div>
            <div>
                1. <strong>Interim Validity:</strong> This Temporary Residence Card is issued to confirm that the holder has completed physical biometrics enrollment for the ECOWAS Residence Card under the authority of the Comptroller General of Immigration.
            </div>
            <div style="margin-top: 3px;">
                2. <strong>Duration:</strong> {{ $card['validity_note'] }}
            </div>
            <div style="margin-top: 3px;">
                3. <strong>Card Replacement:</strong> Upon physical collection of the permanent ECOWAS Residence Card at the designated enrollment center, this temporary card must be surrendered or deemed automatically superseded.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Generated electronically by Nigeria Immigration Service RCIS Portal on {{ now()->timezone(config('app.timezone', 'Africa/Lagos'))->format('d/m/Y H:i') }} WAT.<br>
            Official Template Preview · Directorate of Visa and Residency · All Rights Reserved.
        </div>
    </div>

</body>
</html>

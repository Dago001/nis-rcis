<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Temporary Residence Card - {{ $card['temporary_card_number'] }}</title>
<style>
    @page {
        margin: 8mm 10mm 8mm 10mm;
        size: A4 portrait;
    }
    body {
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        color: #10241a;
        margin: 0;
        padding: 0;
        font-size: 9pt;
        line-height: 1.35;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5mm;
    }
    .header-logo {
        width: 18mm;
        text-align: left;
        vertical-align: middle;
    }
    .header-text {
        text-align: center;
        vertical-align: middle;
    }
    .header-country {
        font-size: 11pt;
        font-weight: bold;
        letter-spacing: 1px;
        color: #062e1b;
        text-transform: uppercase;
    }
    .header-agency {
        font-size: 13pt;
        font-weight: bold;
        letter-spacing: 0.8px;
        color: #0a4a2c;
        text-transform: uppercase;
        margin-top: 1px;
    }
    .header-sub {
        font-size: 7.5pt;
        color: #475569;
        margin-top: 1px;
    }

    /* Prominent 30-Day Validity Banner */
    .validity-container {
        border: 1.5px solid #0a4a2c;
        background-color: #f0fdf4;
        border-radius: 3mm;
        padding: 2.5mm 4mm;
        margin-bottom: 5mm;
        text-align: center;
    }
    .validity-title {
        font-size: 10pt;
        font-weight: bold;
        color: #0a4a2c;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .validity-dates {
        margin-top: 1.5mm;
        font-size: 8.5pt;
        font-weight: bold;
        color: #1e293b;
    }
    .validity-dates .highlight {
        color: #b3261e;
    }

    /* Cards Side by Side */
    .cards-row {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5mm;
    }
    .card-cell {
        width: 50%;
        vertical-align: top;
        text-align: center;
    }
    .card-side-label {
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
        color: #475569;
        letter-spacing: 1px;
        margin-bottom: 1.5mm;
    }

    /* Standard ID-1 Dimensions: 85.6mm x 54.0mm */
    .id1-card {
        position: relative;
        width: 85.6mm;
        height: 54.0mm;
        margin: 0 auto;
        border-radius: 3.4mm;
        overflow: hidden;
        border: 0.3mm solid #0a4a2c;
        background-color: #f6f4ea;
        text-align: left;
    }
    .template-bg {
        position: absolute;
        left: 0;
        top: 0;
        width: 85.6mm;
        height: 54.0mm;
        z-index: 1;
    }
    .temp-card-banner {
        position: absolute;
        left: 2.4mm;
        right: 2.4mm;
        top: 8.6mm;
        background-color: #0a4a2c;
        color: #ffffff;
        font-size: 6pt;
        font-weight: bold;
        text-align: center;
        letter-spacing: 0.8px;
        padding: 0.6mm 0;
        border: 0.25mm solid #c9a13b;
        border-radius: 0.8mm;
        z-index: 5;
        text-transform: uppercase;
    }
    .temp-card-banner-back {
        position: absolute;
        left: 2.4mm;
        right: 2.4mm;
        top: 2.5mm;
        background-color: #0a4a2c;
        color: #ffffff;
        font-size: 6pt;
        font-weight: bold;
        text-align: center;
        letter-spacing: 0.8px;
        padding: 0.6mm 0;
        border: 0.25mm solid #c9a13b;
        border-radius: 0.8mm;
        z-index: 5;
        text-transform: uppercase;
    }

    .photo-box {
        position: absolute;
        left: 2.6mm;
        top: 12.4mm;
        width: 19.2mm;
        height: 24.6mm;
        border-radius: 1.2mm;
        overflow: hidden;
        z-index: 3;
        background-color: #dfe7ee;
    }
    .photo-box img {
        width: 100%;
        height: 100%;
    }
    .sig-box {
        position: absolute;
        left: 2.4mm;
        top: 39.5mm;
        width: 21.3mm;
        height: 5.2mm;
        z-index: 3;
        text-align: center;
    }
    .sig-box img {
        max-width: 100%;
        max-height: 5.2mm;
    }
    .card-no {
        position: absolute;
        right: 2.8mm;
        top: 12.8mm;
        font-family: DejaVu Sans Mono, monospace;
        font-size: 6.2pt;
        font-weight: bold;
        color: #b3261e;
        letter-spacing: 0.5px;
        z-index: 4;
        text-align: right;
    }
    .val-surname {
        position: absolute;
        left: 24.2mm;
        top: 14.2mm;
        width: 44mm;
        font-size: 7pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
        z-index: 4;
    }
    .val-forenames {
        position: absolute;
        left: 24.2mm;
        top: 19.6mm;
        width: 44mm;
        font-size: 7pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
        z-index: 4;
    }
    .val-nationality {
        position: absolute;
        left: 24.2mm;
        top: 26.1mm;
        width: 19.5mm;
        font-size: 5.8pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
        z-index: 4;
    }
    .val-sex {
        position: absolute;
        left: 45.6mm;
        top: 26.1mm;
        width: 16mm;
        font-size: 5.8pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        z-index: 4;
    }
    .val-dob {
        position: absolute;
        left: 24.2mm;
        top: 31.0mm;
        width: 19.5mm;
        font-size: 5.8pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        z-index: 4;
    }
    .val-passport {
        position: absolute;
        left: 45.6mm;
        top: 31.0mm;
        width: 18.5mm;
        font-size: 5.8pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
        z-index: 4;
    }
    .val-issued-on {
        position: absolute;
        left: 24.2mm;
        top: 35.8mm;
        width: 19.5mm;
        font-size: 5.8pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        z-index: 4;
    }
    .val-expires-on {
        position: absolute;
        left: 45.6mm;
        top: 35.8mm;
        width: 18.5mm;
        font-size: 5.8pt;
        font-weight: bold;
        color: #b3261e;
        text-transform: uppercase;
        white-space: nowrap;
        z-index: 4;
    }
    .val-state-code {
        position: absolute;
        left: 24.2mm;
        top: 40.8mm;
        width: 19.5mm;
        font-size: 5.8pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        z-index: 4;
    }
    .nis-watermark {
        position: absolute;
        right: 4.2mm;
        top: 19.6mm;
        width: 9.4mm;
        height: 12.8mm;
        z-index: 2;
        text-align: center;
    }
    .nis-watermark img {
        max-width: 9.4mm;
        max-height: 12.8mm;
        opacity: 0.22;
    }

    /* Back Card Components */
    .qr-box {
        position: absolute;
        left: 3.0mm;
        top: 8.1mm;
        width: 17.5mm;
        height: 19.4mm;
        z-index: 3;
        text-align: center;
    }
    .qr-box img {
        width: 16.5mm;
        height: 16.5mm;
    }
    .val-issued-at {
        position: absolute;
        left: 23.4mm;
        top: 14.3mm;
        width: 20.5mm;
        font-size: 5.4pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
        z-index: 4;
    }
    .val-card-type {
        position: absolute;
        left: 45.5mm;
        top: 14.3mm;
        width: 22.0mm;
        font-size: 5.4pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        z-index: 4;
    }
    .val-domicile {
        position: absolute;
        left: 23.4mm;
        top: 17.8mm;
        width: 58.0mm;
        font-size: 4.8pt;
        font-weight: 600;
        color: #10241a;
        line-height: 1.15;
        max-height: 5.2mm;
        overflow: hidden;
        z-index: 4;
    }
    .val-profession {
        position: absolute;
        left: 23.4mm;
        top: 23.3mm;
        width: 20.5mm;
        font-size: 5.4pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
        z-index: 4;
    }
    .val-height {
        position: absolute;
        left: 45.5mm;
        top: 23.3mm;
        width: 18.5mm;
        font-size: 5.4pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        z-index: 4;
    }
    .val-back-issued-on {
        position: absolute;
        left: 23.4mm;
        top: 27.8mm;
        width: 20.5mm;
        font-size: 5.4pt;
        font-weight: bold;
        color: #10241a;
        text-transform: uppercase;
        white-space: nowrap;
        z-index: 4;
    }
    .val-back-expires-on {
        position: absolute;
        left: 45.5mm;
        top: 27.8mm;
        width: 18.5mm;
        font-size: 5.4pt;
        font-weight: bold;
        color: #b3261e;
        text-transform: uppercase;
        white-space: nowrap;
        z-index: 4;
    }
    .validity-block-back {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 9.8mm;
        background-color: #062e1b;
        color: #ffffff;
        text-align: center;
        z-index: 5;
        padding-top: 1.2mm;
    }
    .validity-block-back .line-1 {
        font-size: 5.2pt;
        font-weight: bold;
        letter-spacing: 0.8px;
        color: #e8cf7a;
        text-transform: uppercase;
    }
    .validity-block-back .line-2 {
        font-size: 4.8pt;
        font-weight: normal;
        margin-top: 0.5mm;
        color: #ffffff;
    }

    /* Summary Details Table */
    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 3mm;
        font-size: 7.5pt;
    }
    .summary-table th, .summary-table td {
        padding: 1.8mm 2.5mm;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
    }
    .summary-table th {
        font-weight: bold;
        color: #475569;
        text-transform: uppercase;
        width: 25%;
        background-color: #f8fafc;
    }
    .summary-table td {
        color: #0f172a;
    }

    /* Notice Box */
    .notice-box {
        margin-top: 4mm;
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        border-radius: 2mm;
        padding: 2.5mm 3.5mm;
        font-size: 6.8pt;
        color: #475569;
        line-height: 1.35;
    }
    .notice-title {
        font-size: 7.2pt;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        margin-bottom: 1mm;
    }

    .footer {
        margin-top: 4mm;
        text-align: center;
        font-size: 6.5pt;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        padding-top: 2mm;
    }
</style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td class="header-logo">
                @if($logo)
                    <img src="{{ $logo }}" style="height: 14mm;" alt="NIS Logo" />
                @endif
            </td>
            <td class="header-text">
                <div class="header-country">Federal Republic of Nigeria</div>
                <div class="header-agency">Nigeria Immigration Service</div>
                <div class="header-sub">Directorate of Visa and Residency &middot; ECOWAS Residence Card System</div>
            </td>
            <td class="header-logo" style="text-align: right;">
                @if($logo)
                    <img src="{{ $logo }}" style="height: 14mm;" alt="NIS Logo" />
                @endif
            </td>
        </tr>
    </table>

    <!-- Prominent 30-Day Validity Banner -->
    <div class="validity-container">
        <div class="validity-title">TEMPORARY RESIDENCE CARD &middot; INTERIM RESIDENCE CREDENTIAL</div>
        <div class="validity-dates">
            <span>VALIDITY: {{ $card['validity_period'] }}</span>
            &nbsp;&nbsp;&nbsp;&nbsp;&vert;&nbsp;&nbsp;&nbsp;&nbsp;
            <span>VALID FROM: <strong>{{ $card['valid_from'] }}</strong></span>
            &nbsp;&nbsp;&nbsp;&nbsp;&vert;&nbsp;&nbsp;&nbsp;&nbsp;
            <span>VALID UNTIL: <strong class="highlight">{{ $card['valid_until'] }}</strong></span>
        </div>
    </div>

    <!-- Front & Back Cards Side-by-Side (ID-1 proportions: 85.6mm x 54.0mm) -->
    <table class="cards-row">
        <tr>
            <!-- CARD FRONT -->
            <td class="card-cell">
                <div class="card-side-label">Card Front</div>
                <div class="id1-card">
                    @if($frontTemplate)
                        <img src="{{ $frontTemplate }}" class="template-bg" alt="" />
                    @endif

                    <!-- Identification Banner -->
                    <div class="temp-card-banner">Temporary Residence Card</div>

                    <!-- Photo -->
                    <div class="photo-box">
                        @if($photo)
                            <img src="{{ $photo }}" alt="Applicant Photograph" />
                        @endif
                    </div>

                    <!-- Signature -->
                    <div class="sig-box">
                        @if($signature)
                            <img src="{{ $signature }}" alt="Holder Signature" />
                        @endif
                    </div>

                    <!-- Card / Ref Number -->
                    <div class="card-no">{{ $card['temporary_card_number'] }}</div>

                    <!-- Particulars -->
                    <div class="val-surname">{{ $card['surname'] }}</div>
                    <div class="val-forenames">{{ $card['forenames'] }}</div>
                    <div class="val-nationality">{{ $card['nationality'] }}</div>
                    <div class="val-sex">{{ $card['sex'] }}</div>
                    <div class="val-dob">{{ $card['dob_card'] }}</div>
                    <div class="val-passport">{{ $card['passport_number'] }}</div>
                    <div class="val-issued-on">{{ $card['issue_date_card'] }}</div>
                    <div class="val-expires-on">{{ $card['expiry_date_card'] }}</div>
                    <div class="val-state-code">{{ $card['state_code'] }}</div>

                    <!-- Watermark -->
                    <div class="nis-watermark">
                        @if($logo)
                            <img src="{{ $logo }}" alt="" />
                        @endif
                    </div>
                </div>
            </td>

            <!-- CARD BACK -->
            <td class="card-cell">
                <div class="card-side-label">Card Back</div>
                <div class="id1-card">
                    @if($backTemplate)
                        <img src="{{ $backTemplate }}" class="template-bg" alt="" />
                    @endif

                    <!-- Identification Banner Back -->
                    <div class="temp-card-banner-back">Temporary Residence Card</div>

                    <!-- Verification QR -->
                    <div class="qr-box">
                        <img src="{{ $card['qr_svg'] }}" alt="Verification QR Code" />
                    </div>

                    <!-- Back Particulars -->
                    <div class="val-issued-at">{{ $card['enrollment_center'] }}</div>
                    <div class="val-card-type">{{ $card['card_type'] }}</div>
                    <div class="val-domicile">{{ $card['domicile_full'] }}</div>
                    <div class="val-profession">{{ $card['profession'] }}</div>
                    <div class="val-height">{{ $card['height'] }}</div>
                    <div class="val-back-issued-on">{{ $card['issue_date_card'] }}</div>
                    <div class="val-back-expires-on">{{ $card['expiry_date_card'] }}</div>

                    <!-- Bottom Validity Block (Replacing MRZ) -->
                    <div class="validity-block-back">
                        <div class="line-1">TEMPORARY RESIDENCE CARD &middot; VALIDITY: {{ $card['validity_period'] }}</div>
                        <div class="line-2">VALID FROM: {{ $card['issue_date_card'] }} &nbsp;&vert;&nbsp; VALID UNTIL: {{ $card['expiry_date_card'] }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Verified Particulars Summary Table -->
    <table class="summary-table">
        <tr>
            <th>Document Ref</th>
            <td><strong>{{ $card['temporary_card_number'] }}</strong></td>
            <th>Application No</th>
            <td>{{ $card['application_number'] }}</td>
        </tr>
        <tr>
            <th>Full Name</th>
            <td><strong>{{ $card['applicant_name'] }}</strong></td>
            <th>Nationality</th>
            <td>{{ $card['nationality'] }}</td>
        </tr>
        <tr>
            <th>Passport Number</th>
            <td>{{ $card['passport_number'] }}</td>
            <th>Date of Birth</th>
            <td>{{ $card['date_of_birth'] }} (Sex: {{ $card['sex'] }})</td>
        </tr>
        <tr>
            <th>Enrollment Center</th>
            <td>{{ $card['enrollment_center'] }}</td>
            <th>Biometrics Captured</th>
            <td>{{ $card['valid_from'] }}</td>
        </tr>
        <tr>
            <th>Validity Period</th>
            <td><strong>VALIDITY: {{ $card['validity_period'] }}</strong></td>
            <th>Validity Expiry</th>
            <td><strong style="color: #b3261e;">{{ $card['valid_until'] }}</strong></td>
        </tr>
    </table>

    <!-- Regulatory Notice -->
    <div class="notice-box">
        <div class="notice-title">Official Regulatory &amp; Legal Notice:</div>
        <div>
            1. <strong>Interim Legal Effect:</strong> This official Temporary Residence Card certifies that the holder has completed mandatory biometric enrollment and is lawful resident in Nigeria pending production and issuance of the permanent ECOWAS Residence Card.
        </div>
        <div style="margin-top: 1mm;">
            2. <strong>Validity Policy:</strong> VALIDITY: {{ $card['validity_period'] }} &mdash; Valid from {{ $card['valid_from'] }} until {{ $card['valid_until'] }} (30 calendar days from confirmed biometric enrollment date). Reprinting does not extend or reset validity.
        </div>
        <div style="margin-top: 1mm;">
            3. <strong>Card Collection:</strong> When notified that your permanent card is ready, present your international passport and this temporary card at your designated enrollment center to collect your permanent card.
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        Federal Republic of Nigeria &middot; Nigeria Immigration Service &middot; Electronic Credential &middot; All Rights Reserved.
    </div>

</body>
</html>

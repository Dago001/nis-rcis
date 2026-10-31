<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TemporaryResidenceCardService
{
    public function getValidityDays(): int
    {
        return (int) config('nis.temporary_card_validity_days', 30);
    }

    public function isEligible(Application $application): bool
    {
        // 1. Biometrics must be successfully completed and confirmed
        // 2. Current application status must be BIOMETRICS_CAPTURED, READY_FOR_COLLECTION, or ISSUED
        if ($application->biometrics_captured_at === null) {
            return false;
        }

        return in_array($application->status, [
            ApplicationStatus::BiometricsCaptured,
            ApplicationStatus::ReadyForCollection,
            ApplicationStatus::Issued,
        ], true);
    }

    public function assertEligible(Application $application): void
    {
        if (! $this->isEligible($application)) {
            throw ValidationException::withMessages([
                'temporary_card' => 'A Temporary Residence Card is only available after final approval and successful completion of physical biometrics capture at the designated enrollment center.',
            ]);
        }
    }

    public function getData(Application $application, DocumentStorage $storage): array
    {
        $this->assertEligible($application);

        $application->loadMissing(['enrollmentCenter', 'principal']);

        $fullName = trim("{$application->surname}, {$application->forenames}");
        $category = strtoupper(trim($application->category ?? ($application->principal ? $application->principal->category : 'WORK')));

        $validityDays = $this->getValidityDays();
        // Use Africa/Lagos timezone for confirmed biometrics capture date & validity calculation
        $capturedAt = $application->biometrics_captured_at
            ? Carbon::parse($application->biometrics_captured_at)->setTimezone('Africa/Lagos')
            : Carbon::now('Africa/Lagos');

        $issueDate = $capturedAt->copy();
        $expiryDate = $capturedAt->copy()->addDays($validityDays);

        $temporaryCardNumber = "TRC-{$application->application_number}";
        $qrPayload = "NIS-TRC|REF:{$application->reference_number}|APP:{$application->application_number}|STATUS:VALID|VALID_DAYS:{$validityDays}|VALID_FROM:{$issueDate->toDateString()}|VALID_UNTIL:{$expiryDate->toDateString()}";

        $qrSvg = $this->generateQrCodeSvg($qrPayload);

        // State code calculation for front card
        $stateMap = [
            'ABUJA' => 'FC', 'FEDERAL CAPITAL TERRITORY' => 'FC', 'FCT' => 'FC',
            'ABIA' => 'AB', 'ADAMAWA' => 'AD', 'AKWA IBOM' => 'AK', 'ANAMBRA' => 'AN',
            'BAUCHI' => 'BA', 'BAYELSA' => 'BY', 'BENUE' => 'BN', 'BORNO' => 'BO',
            'CROSS RIVER' => 'CR', 'DELTA' => 'DE', 'EBONYI' => 'EB', 'EDO' => 'ED',
            'EKITI' => 'EK', 'ENUGU' => 'EN', 'GOMBE' => 'GO', 'IMO' => 'IM',
            'JIGAWA' => 'JI', 'KADUNA' => 'KD', 'KANO' => 'KN', 'KATSINA' => 'KT',
            'KEBBI' => 'KB', 'KOGI' => 'KO', 'KWARA' => 'KW', 'LAGOS' => 'LA',
            'NASARAWA' => 'NA', 'NIGER' => 'NI', 'OGUN' => 'OG', 'ONDO' => 'OD',
            'OSUN' => 'OS', 'OYO' => 'OY', 'PLATEAU' => 'PL', 'RIVERS' => 'RV',
            'SOKOTO' => 'SO', 'TARABA' => 'TA', 'YOBE' => 'YO', 'ZAMFARA' => 'ZA',
        ];
        $stateCode = $stateMap[strtoupper(trim($application->domicile_state ?? ''))] ?? 'FC';

        // Domicile string
        $domParts = array_filter([
            trim($application->domicile ?? ''),
            $application->domicile_lga ? 'LGA: ' . trim($application->domicile_lga) : null,
            $application->domicile_state ? trim($application->domicile_state) . ' STATE' : null,
        ]);
        $fullDomicile = !empty($domParts) ? implode(', ', $domParts) : '—';

        // Height formatting
        $rawHeight = trim($application->height ?? '');
        $height = '—';
        if (!empty($rawHeight)) {
            if (preg_match('/[a-z]$/i', $rawHeight)) {
                $height = preg_replace('/\s*(cm|m)$/i', ' $1', strtolower($rawHeight));
            } else {
                $height = ((float) $rawHeight < 3) ? "{$rawHeight} m" : "{$rawHeight} cm";
            }
        }

        $cardType = match ($category) {
            'BUSINESS' => 'TYPE 02 - BUSINESS RESIDENCE',
            'STUDENT' => 'TYPE 03 - STUDENT RESIDENCE',
            'RELIGIOUS' => 'TYPE 04 - RELIGIOUS RESIDENCE',
            default => 'TYPE 01 - RESIDENCE CARD',
        };

        return [
            'id' => $application->id,
            'temporary_card_number' => $temporaryCardNumber,
            'application_number' => $application->application_number,
            'reference_number' => $application->reference_number,
            'applicant_name' => $fullName,
            'surname' => $application->surname,
            'forenames' => $application->forenames,
            'nationality' => $application->nationality,
            'date_of_birth' => $application->date_of_birth?->format('d/m/Y') ?? 'Not Provided',
            'dob_card' => $application->date_of_birth ? strtoupper($application->date_of_birth->format('d M Y')) : '—',
            'sex' => $application->sex ?? '—',
            'passport_number' => $application->passport_number,
            'category' => $category,
            'card_type' => $cardType,
            'domicile' => $application->domicile,
            'domicile_state' => $application->domicile_state,
            'domicile_lga' => $application->domicile_lga,
            'domicile_full' => $fullDomicile,
            'state_code' => $stateCode,
            'profession' => $application->profession ?? '—',
            'height' => $height,
            'enrollment_center' => $application->enrollmentCenter?->name ?? 'NIS Headquarters, Abuja',
            'enrollment_state' => $application->enrollmentCenter?->state ?? 'Federal Republic of Nigeria',
            'biometrics_captured_at' => $application->biometrics_captured_at?->toIso8601String(),
            'issue_date' => $issueDate->format('d/m/Y'),
            'expiry_date' => $expiryDate->format('d/m/Y'),
            'issue_date_card' => strtoupper($issueDate->format('d M Y')),
            'expiry_date_card' => strtoupper($expiryDate->format('d M Y')),
            'validity_days' => $validityDays,
            'validity_period' => "{$validityDays} DAYS",
            'validity_heading' => "VALIDITY: {$validityDays} DAYS",
            'valid_from' => $issueDate->format('j F Y'),
            'valid_until' => $expiryDate->format('j F Y'),
            'validity_note' => "Valid for {$validityDays} calendar days from confirmed biometrics completion date pending permanent residence card production and collection under official Nigeria Immigration Service regulations.",
            'status' => $application->status->value,
            'status_label' => $application->status->label(false),
            'photo_url' => $storage->temporaryUrlForPath($application->photo_path),
            'signature_url' => $storage->temporaryUrlForPath($application->signature_path),
            'qr_payload' => $qrPayload,
            'qr_svg' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'is_development_preview' => false,
            'template_status' => 'Official Temporary Residence Card',
            'disclaimer' => 'Official Temporary Residence Card — Issued under official Nigeria Immigration Service regulations.',
            'is_dependent' => (bool) $application->principal_application_id,
            'principal_application_number' => $application->principal?->application_number,
        ];
    }

    public function generateQrCodeSvg(string $payload): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(140, 1),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            return $writer->writeString($payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('QR generation error in TemporaryResidenceCardService: ' . $e->getMessage());
            return '<svg width="140" height="140" xmlns="http://www.w3.org/2000/svg"><rect width="140" height="140" fill="#f8fafc"/><text x="10" y="70" font-size="10" fill="#64748b">QR CODE</text></svg>';
        }
    }

    public function renderPdf(Application $application, DocumentStorage $storage): string
    {
        $data = $this->getData($application, $storage);

        // Get base64 encoded logo
        $logoPath = public_path('images/nis-logo.png');
        $logoData = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
            : '';

        // Get base64 encoded front template background
        $frontTplPath = public_path('card/card-front-template.jpg');
        $frontTplData = file_exists($frontTplPath)
            ? 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($frontTplPath))
            : '';

        // Get base64 encoded back template background
        $backTplPath = public_path('card/card-back-template.png');
        $backTplData = file_exists($backTplPath)
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($backTplPath))
            : '';

        // Get base64 encoded photo
        $photoBytes = $storage->getBytesForPath($application->photo_path);
        $photoData = null;
        if ($photoBytes) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($photoBytes) ?: 'image/jpeg';
            $photoData = 'data:' . $mime . ';base64,' . base64_encode($photoBytes);
        }

        // Get base64 encoded signature
        $sigBytes = $storage->getBytesForPath($application->signature_path);
        $sigData = null;
        if ($sigBytes) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($sigBytes) ?: 'image/png';
            $sigData = 'data:' . $mime . ';base64,' . base64_encode($sigBytes);
        }

        $html = view('pdf.temporary-card', [
            'card' => $data,
            'application' => $application,
            'logo' => $logoData,
            'frontTemplate' => $frontTplData,
            'backTemplate' => $backTplData,
            'photo' => $photoData,
            'signature' => $sigData,
        ])->render();

        $options = new Options();
        $options->setChroot(public_path());
        $options->setIsRemoteEnabled(true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}

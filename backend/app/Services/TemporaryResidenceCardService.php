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
use Illuminate\Validation\ValidationException;

class TemporaryResidenceCardService
{
    public const VALIDITY_DAYS = 90;

    public function isEligible(Application $application): bool
    {
        // 1. Must have had final approval (decided_at not null or in approved statuses)
        // 2. Biometrics must be successfully completed and confirmed
        // 3. Current application status must be BIOMETRICS_CAPTURED, READY_FOR_COLLECTION, or ISSUED
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
        $category = $application->category ?? ($application->principal ? $application->principal->category : 'WORK');
        $capturedAt = $application->biometrics_captured_at ?? now();
        $issueDate = $capturedAt->copy();
        $expiryDate = $capturedAt->copy()->addDays(self::VALIDITY_DAYS);

        $temporaryCardNumber = "TRC-{$application->application_number}";
        $qrPayload = "NIS-TRC|REF:{$application->reference_number}|APP:{$application->application_number}|STATUS:VALID|VALID_DAYS:".self::VALIDITY_DAYS;

        $qrSvg = $this->generateQrCodeSvg($qrPayload);

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
            'sex' => $application->sex ?? '—',
            'passport_number' => $application->passport_number,
            'category' => $category,
            'domicile' => $application->domicile,
            'domicile_state' => $application->domicile_state,
            'domicile_lga' => $application->domicile_lga,
            'enrollment_center' => $application->enrollmentCenter?->name ?? 'Nigeria Immigration Service',
            'enrollment_state' => $application->enrollmentCenter?->state ?? 'Federal Republic of Nigeria',
            'biometrics_captured_at' => $application->biometrics_captured_at?->toIso8601String(),
            'issue_date' => $issueDate->format('d/m/Y'),
            'expiry_date' => $expiryDate->format('d/m/Y'),
            'validity_period' => self::VALIDITY_DAYS . ' Days',
            'validity_note' => 'Valid for ninety (90) days from biometrics completion date pending permanent residence card production and collection under gazetted Nigeria Immigration Service rules.',
            'status' => $application->status->value,
            'status_label' => $application->status->label(false),
            'photo_url' => $storage->temporaryUrlForPath($application->photo_path),
            'signature_url' => $storage->temporaryUrlForPath($application->signature_path),
            'qr_payload' => $qrPayload,
            'qr_svg' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'is_development_preview' => true,
            'template_status' => 'Official Template Configuration: Development Preview',
            'disclaimer' => 'Official Template Configuration: Development Preview — Pending statutory commencement / official gazetted issuance rules.',
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

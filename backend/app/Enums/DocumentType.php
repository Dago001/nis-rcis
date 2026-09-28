<?php

namespace App\Enums;

enum DocumentType: string
{
    case Photo = 'photo';
    case Signature = 'signature';
    case PassportCopy = 'passport_copy';
    case ResidenceVisa = 'residence_visa';
    case QuotaApproval = 'quota_approval';
    case DomicileProof = 'domicile_proof';
    case Additional = 'additional';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Passport photograph',
            self::Signature => 'Signature',
            self::PassportCopy => 'International passport data page',
            self::ResidenceVisa => 'Subject-to-Regularization / residence visa',
            self::QuotaApproval => 'Expatriate quota approval',
            self::DomicileProof => 'Proof of domicile',
            self::Additional => 'Additional document',
        };
    }

    /** Documents an applicant must upload before submitting. */
    public static function requiredForSubmission(): array
    {
        return [self::Photo, self::PassportCopy, self::ResidenceVisa];
    }

    /** Types applicants may upload (signature is captured at the desk). */
    public static function applicantUploadable(): array
    {
        return [self::Photo, self::PassportCopy, self::ResidenceVisa, self::QuotaApproval, self::DomicileProof, self::Additional];
    }

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Photo, self::Signature => ['image/jpeg', 'image/png'],
            default => ['image/jpeg', 'image/png', 'application/pdf'],
        };
    }
}

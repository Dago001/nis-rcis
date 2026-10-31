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
    case CacCertificate = 'cac_certificate';
    case AdmissionLetter = 'admission_letter';
    case AcceptanceLetter = 'acceptance_letter';
    case ProofOfPayment = 'proof_of_payment';
    case AppointmentLetter = 'appointment_letter';
    case ReligiousInvitation = 'religious_invitation';
    case ReligiousAssignment = 'religious_sponsorship';
    case ReligiousActivitiesDetails = 'religious_activities_details';
    case ReligiousRegistration = 'religious_registration';
    case ReligiousAffiliation = 'religious_affiliation';
    case EmergencyContactIdNigeria = 'emergency_contact_id_nigeria';
    case EmergencyContactIdHome = 'emergency_contact_id_home';
    case Additional = 'additional';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Passport photograph',
            self::Signature => 'Signature',
            self::PassportCopy => 'International passport data page / National ID / ECOWAS Travel Certificate',
            self::ResidenceVisa => 'Subject-to-Regularization / residence visa',
            self::QuotaApproval => 'Expatriate quota approval',
            self::DomicileProof => 'Proof of Residence (Utility Bill / Tenancy Agreement)',
            self::CacCertificate => 'CAC Registration Document (Optional)',
            self::AdmissionLetter => 'Institution Admission Letter',
            self::AcceptanceLetter => 'Acceptance Letter',
            self::ProofOfPayment => 'Proof of Payment',
            self::AppointmentLetter => 'Appointment Letter / Contract Letter',
            self::ReligiousInvitation => 'Appointment Letter / Contract Letter',
            self::ReligiousAssignment => 'Letter of Religious Assignment or Sponsorship',
            self::ReligiousActivitiesDetails => 'Details of Intended Religious Activities',
            self::ReligiousRegistration => 'Evidence of Host Organization Registration or Recognition',
            self::ReligiousAffiliation => "Evidence of Applicant's Religious Role or Affiliation",
            self::EmergencyContactIdNigeria => 'Emergency Contact in Nigeria — Identification Document',
            self::EmergencyContactIdHome => 'Emergency Contact in Home Country — Identification Document',
            self::Additional => 'Additional document',
        };
    }

    /** Documents an applicant must upload before submitting. */
    public static function requiredForSubmission(): array
    {
        return [
            self::Photo,
            self::PassportCopy,
            self::DomicileProof,
        ];
    }

    /** Types applicants may upload (signature is captured at the desk). */
    public static function applicantUploadable(): array
    {
        return [
            self::Photo,
            self::PassportCopy,
            self::ResidenceVisa,
            self::QuotaApproval,
            self::DomicileProof,
            self::CacCertificate,
            self::AdmissionLetter,
            self::AcceptanceLetter,
            self::ProofOfPayment,
            self::AppointmentLetter,
            self::ReligiousInvitation,
            self::ReligiousAssignment,
            self::ReligiousActivitiesDetails,
            self::ReligiousRegistration,
            self::ReligiousAffiliation,
            self::EmergencyContactIdNigeria,
            self::EmergencyContactIdHome,
            self::Additional,
        ];
    }

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Photo, self::Signature => [
                'image/jpeg',
                'image/jpg',
                'image/pjpeg',
                'image/png',
                'image/x-png',
            ],
            default => [
                'image/jpeg',
                'image/jpg',
                'image/pjpeg',
                'image/png',
                'image/x-png',
                'application/pdf',
            ],
        };
    }
}

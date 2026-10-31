<?php

namespace App\Enums;

/**
 * Residence card application workflow (updated per official NIS specification):
 *
 *   Application Submitted (PENDING_APPROVAL)
 *        │  ▲
 *   vet  │  │ query / respond
 *        ▼  │
 *      VETTED (Eligible for Biometrics Enrollment)
 *        │  ▲
 *capture │  │ query / respond
 *        ▼  │
 *   BIOMETRICS_CAPTURED (Biometrics Completed, Awaiting Approval)
 *        │  ▲
 *approve │  │ query / respond
 *        ▼  │
 *     APPROVED (Card in Production)
 *        │
 *  print │
 *        ▼
 *   READY_FOR_COLLECTION (Awaiting Issuance)
 *        │
 *  issue │
 *        ▼
 *     ISSUED
 */
enum ApplicationStatus: string
{
    case PendingApproval = 'PENDING_APPROVAL';
    case Vetted = 'VETTED';
    case Queried = 'QUERIED';
    case Rejected = 'REJECTED';
    case BiometricsCaptured = 'BIOMETRICS_CAPTURED';
    case Approved = 'APPROVED';
    case ApprovedForBiometrics = 'APPROVED_FOR_BIOMETRICS'; // Preserved for historical application records
    case ReadyForCollection = 'READY_FOR_COLLECTION';
    case Issued = 'ISSUED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingApproval => [self::Vetted, self::Queried],
            self::Vetted => [self::BiometricsCaptured, self::Queried],
            self::BiometricsCaptured => [self::Approved, self::Queried],
            self::Approved => [self::ReadyForCollection],
            self::ApprovedForBiometrics => [self::BiometricsCaptured, self::Approved, self::ReadyForCollection],
            self::Queried => [self::PendingApproval, self::Vetted, self::BiometricsCaptured],
            self::ReadyForCollection => [self::Issued],
            self::Rejected, self::Issued => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Rejected], true);
    }

    public function label(bool $isStaff = true): string
    {
        return match ($this) {
            self::PendingApproval => 'Awaiting Vetting',
            self::Vetted => $isStaff ? 'Vetted — Eligible for Biometrics' : 'Vetted — Attend Biometrics Appointment',
            self::Queried => 'Queried — action required',
            self::Rejected => 'Rejected',
            self::BiometricsCaptured => $isStaff ? 'Biometrics Completed — Awaiting Approval' : 'Biometrics Completed — Under Final Review',
            self::Approved => 'Approved — Card in Production',
            self::ApprovedForBiometrics => 'Approved',
            self::ReadyForCollection => 'Ready for collection',
            self::Issued => 'Card collected',
        };
    }

    /** Position on the applicant's progress tracker (legacy track.php steps). */
    public function trackerStep(): int
    {
        return match ($this) {
            self::PendingApproval, self::Queried, self::Rejected => 2,
            self::Vetted => 3,
            self::BiometricsCaptured => 4,
            self::Approved, self::ApprovedForBiometrics => 4,
            self::ReadyForCollection, self::Issued => 5,
        };
    }
}

<?php

namespace App\Enums;

/**
 * Residence card application workflow (preserved from the legacy system):
 *
 *   PENDING_APPROVAL ──approve──▶ APPROVED_FOR_BIOMETRICS ──capture──▶ BIOMETRICS_CAPTURED
 *        │  ▲                                                               │
 *      query│ │applicant responds                               card approved & printed,
 *        ▼  │                                                    marked ready
 *      QUERIED                                                              ▼
 *        │                                                        READY_FOR_COLLECTION
 *      reject (also from PENDING_APPROVAL)                                  │ collected
 *        ▼                                                                  ▼
 *      REJECTED                                                          ISSUED
 */
enum ApplicationStatus: string
{
    case PendingApproval = 'PENDING_APPROVAL';
    case Queried = 'QUERIED';
    case Rejected = 'REJECTED';
    case ApprovedForBiometrics = 'APPROVED_FOR_BIOMETRICS';
    case BiometricsCaptured = 'BIOMETRICS_CAPTURED';
    case ReadyForCollection = 'READY_FOR_COLLECTION';
    case Issued = 'ISSUED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingApproval => [self::ApprovedForBiometrics, self::Queried, self::Rejected],
            self::Queried => [self::PendingApproval, self::Rejected],
            self::ApprovedForBiometrics => [self::BiometricsCaptured],
            self::BiometricsCaptured => [self::ReadyForCollection],
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

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending approval',
            self::Queried => 'Queried — action required',
            self::Rejected => 'Rejected',
            self::ApprovedForBiometrics => 'Approved for biometrics',
            self::BiometricsCaptured => 'Biometrics captured — card in production',
            self::ReadyForCollection => 'Ready for collection',
            self::Issued => 'Card collected',
        };
    }

    /** Position on the applicant's progress tracker (legacy track.php steps). */
    public function trackerStep(): int
    {
        return match ($this) {
            self::PendingApproval, self::Queried, self::Rejected => 2,
            self::ApprovedForBiometrics => 3,
            self::BiometricsCaptured => 4,
            self::ReadyForCollection, self::Issued => 5,
        };
    }
}

<?php

namespace App\Enums;

/**
 * Residence card lifecycle (legacy card-details.php / renew-card.php):
 *
 *   APPROVED (created at biometrics, awaiting final approval)
 *     ├─approve──▶ ISSUED ──renew──▶ RENEWED ──renew──▶ RENEWED …
 *     └─query───▶ QUERIED ──corrected──▶ APPROVED
 *   ISSUED | RENEWED ──revoke──▶ REVOKED ──reinstate (SuperAdmin)──▶ ISSUED
 *
 * EXPIRED is derived from expires_on, never stored.
 */
enum CardStatus: string
{
    case Approved = 'APPROVED';
    case Queried = 'QUERIED';
    case Issued = 'ISSUED';
    case Renewed = 'RENEWED';
    case Revoked = 'REVOKED';

    public function isActive(): bool
    {
        return in_array($this, [self::Issued, self::Renewed], true);
    }
}

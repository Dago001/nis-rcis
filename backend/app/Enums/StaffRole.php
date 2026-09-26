<?php

namespace App\Enums;

enum StaffRole: string
{
    case SuperAdmin = 'SuperAdmin';
    case ApprovingOfficer = 'ApprovingOfficer';
    case IssuingOfficer = 'IssuingOfficer';
    case Inspector = 'Inspector';
    case Auditor = 'Auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::ApprovingOfficer => 'Approving Officer',
            self::IssuingOfficer => 'Issuing Officer',
            self::Inspector => 'Inspector',
            self::Auditor => 'Auditor',
        };
    }
}

<?php

namespace App\Enums;

enum StaffRole: string
{
    case SuperAdmin = 'SuperAdmin';
    case Admin = 'Admin';
    case Supervisor = 'Supervisor';
    case VettingOfficer = 'VettingOfficer';
    case DataEntryOfficer = 'DataEntryOfficer';
    case ApprovingOfficer = 'ApprovingOfficer';
    case EnrollmentOfficer = 'EnrollmentOfficer';
    case ProductionOfficer = 'ProductionOfficer';
    case IssuingOfficer = 'IssuingOfficer';
    // Legacy fallback roles
    case Inspector = 'Inspector';
    case Auditor = 'Auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Supervisor => 'Supervisor',
            self::VettingOfficer, self::DataEntryOfficer => 'Vetting Officer',
            self::ApprovingOfficer => 'Approving Officer',
            self::EnrollmentOfficer => 'Enrollment Officer',
            self::ProductionOfficer => 'Production Officer',
            self::IssuingOfficer => 'Issuing Officer',
            self::Inspector => 'Inspector',
            self::Auditor => 'Auditor',
        };
    }
}

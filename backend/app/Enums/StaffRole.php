<?php

namespace App\Enums;

enum StaffRole: string
{
    // Official NIS Staff Roles
    case SuperAdmin = 'SuperAdmin';
    case HeadquartersAdmin = 'HeadquartersAdmin';
    case ZonalAdmin = 'ZonalAdmin';
    case CommandAdmin = 'CommandAdmin';
    case VettingOfficer = 'VettingOfficer';
    case ApprovingOfficer = 'ApprovingOfficer';
    case BiometricsOfficer = 'BiometricsOfficer';
    case CardProductionOfficer = 'CardProductionOfficer';
    case CardDistributionOfficer = 'CardDistributionOfficer';
    case CardReceivingOfficer = 'CardReceivingOfficer';
    case CardIssuanceOfficer = 'CardIssuanceOfficer';
    case ReportingAuditOfficer = 'ReportingAuditOfficer';

    // Legacy fallback roles for backward database compatibility
    case Admin = 'Admin';
    case Supervisor = 'Supervisor';
    case DataEntryOfficer = 'DataEntryOfficer';
    case EnrollmentOfficer = 'EnrollmentOfficer';
    case ProductionOfficer = 'ProductionOfficer';
    case IssuingOfficer = 'IssuingOfficer';
    case Inspector = 'Inspector';
    case Auditor = 'Auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::HeadquartersAdmin => 'Headquarters Administrator',
            self::ZonalAdmin => 'Zonal Administrator',
            self::CommandAdmin => 'Command Administrator',
            self::VettingOfficer => 'Vetting Officer',
            self::ApprovingOfficer => 'Approving Officer',
            self::BiometricsOfficer => 'Biometrics Officer',
            self::CardProductionOfficer => 'Card Production Officer',
            self::CardDistributionOfficer => 'Card Distribution Officer',
            self::CardReceivingOfficer => 'Card Receiving Officer',
            self::CardIssuanceOfficer => 'Issuance Officer',
            self::ReportingAuditOfficer => 'Reporting / Audit Officer',

            // Legacy labels
            self::Admin => 'Headquarters Administrator',
            self::Supervisor => 'Supervisor',
            self::DataEntryOfficer => 'Vetting Officer',
            self::EnrollmentOfficer => 'Biometrics Officer',
            self::ProductionOfficer => 'Card Production Officer',
            self::IssuingOfficer => 'Issuance Officer',
            self::Inspector => 'Inspector',
            self::Auditor => 'Reporting / Audit Officer',
        };
    }

    public function isHeadquartersRole(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::HeadquartersAdmin,
            self::Admin,
            self::CardProductionOfficer,
            self::ProductionOfficer,
            self::CardDistributionOfficer,
        ], true);
    }

    public function isZonalRole(): bool
    {
        return $this === self::ZonalAdmin;
    }

    public function isCommandRole(): bool
    {
        return in_array($this, [
            self::CommandAdmin,
            self::CardReceivingOfficer,
            self::CardIssuanceOfficer,
            self::IssuingOfficer,
            self::BiometricsOfficer,
            self::EnrollmentOfficer,
        ], true);
    }
}

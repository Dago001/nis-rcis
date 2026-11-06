<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * An NIS staff officer.
 */
class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username', 'fullname', 'rank', 'service_number', 'email', 'password',
        'role', 'command', 'headquarters_unit', 'state', 'lga', 'zone_id', 'command_id', 'enrollment_center_id',
        'is_active', 'geofence_exempt', 'must_change_password', 'photo_path',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_last_step'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'is_active' => 'boolean',
            'geofence_exempt' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Role checking with support for official NIS roles and aliases.
     * SuperAdmin holds every role.
     */
    public function hasRole(StaffRole ...$roles): bool
    {
        if ($this->role === StaffRole::SuperAdmin) {
            return true;
        }

        $aliases = [
            StaffRole::HeadquartersAdmin->value => [StaffRole::Admin],
            StaffRole::Admin->value => [StaffRole::HeadquartersAdmin],
            StaffRole::BiometricsOfficer->value => [StaffRole::EnrollmentOfficer],
            StaffRole::EnrollmentOfficer->value => [StaffRole::BiometricsOfficer],
            StaffRole::CardProductionOfficer->value => [StaffRole::ProductionOfficer],
            StaffRole::ProductionOfficer->value => [StaffRole::CardProductionOfficer],
            StaffRole::CardIssuanceOfficer->value => [StaffRole::IssuingOfficer],
            StaffRole::IssuingOfficer->value => [StaffRole::CardIssuanceOfficer],
            StaffRole::ReportingAuditOfficer->value => [StaffRole::Auditor],
            StaffRole::Auditor->value => [StaffRole::ReportingAuditOfficer],
        ];

        foreach ($roles as $r) {
            if ($this->role === $r) {
                return true;
            }
            if (isset($aliases[$r->value]) && in_array($this->role, $aliases[$r->value], true)) {
                return true;
            }
        }

        return false;
    }

    public function isHeadquarters(): bool
    {
        if (in_array($this->role, [
            StaffRole::SuperAdmin,
            StaffRole::HeadquartersAdmin,
            StaffRole::Admin,
            StaffRole::CardProductionOfficer,
            StaffRole::ProductionOfficer,
            StaffRole::CardDistributionOfficer,
        ], true)) {
            return true;
        }

        // If explicitly assigned without zone or command, or command specifies Headquarters
        if ($this->command_id === null && $this->zone_id === null) {
            return true;
        }

        $cmdUpper = strtoupper($this->command ?? '');
        return str_contains($cmdUpper, 'HEADQUARTERS') || str_contains($cmdUpper, 'NATIONAL') || str_contains($cmdUpper, 'SHQ');
    }

    public function isZonal(): bool
    {
        return ! $this->isHeadquarters() && $this->zone_id !== null && $this->command_id === null;
    }

    public function isCommand(): bool
    {
        return ! $this->isHeadquarters() && $this->command_id !== null;
    }

    public function canAccessApplication(Application $application): bool
    {
        if ($this->isHeadquarters()) {
            return true;
        }

        $application->loadMissing(['enrollmentCenter.zone', 'actualEnrollmentCenter.zone', 'cardDistribution']);
        $center = $application->actualEnrollmentCenter ?: $application->enrollmentCenter;

        if ($this->isZonal()) {
            if ($center && $center->zone_id === $this->zone_id) {
                return true;
            }
            if ($application->actual_enrollment_zone && $this->zone) {
                return $application->actual_enrollment_zone === $this->zone->name || $application->actual_enrollment_zone === $this->zone->code;
            }
            if ($application->cardDistribution && $application->cardDistribution->destination_zone_id === $this->zone_id) {
                return true;
            }
            return false;
        }

        if ($this->isCommand()) {
            if ($application->command_id && (int) $application->command_id === (int) $this->command_id) {
                return true;
            }
            if ($center && (int) $center->command_id === (int) $this->command_id) {
                return true;
            }
            if ($application->actual_enrollment_command && $this->nisCommand) {
                return $application->actual_enrollment_command === $this->nisCommand->name || $application->actual_enrollment_command === $this->nisCommand->code;
            }
            if ($application->cardDistribution && $application->cardDistribution->destination_command_id === $this->command_id) {
                return true;
            }
            return false;
        }

        if ($this->state && ($application->domicile_state === $this->state || ($center && $center->state === $this->state))) {
            return true;
        }

        return false;
    }

    public function canAccessCard(ResidenceCard $card): bool
    {
        if ($this->isHeadquarters()) {
            return true;
        }

        if ($card->relationLoaded('application') && $card->application) {
            return $this->canAccessApplication($card->application);
        }

        $card->loadMissing(['actualEnrollmentCenter.zone', 'distribution']);
        $center = $card->actualEnrollmentCenter;

        if ($this->isZonal()) {
            if ($center && $center->zone_id === $this->zone_id) {
                return true;
            }
            if ($card->enrollment_zone && $this->zone) {
                return $card->enrollment_zone === $this->zone->name;
            }
            if ($card->distribution && $card->distribution->destination_zone_id === $this->zone_id) {
                return true;
            }
            return false;
        }

        if ($this->isCommand()) {
            if ($card->application && (int) $card->application->command_id === (int) $this->command_id) {
                return true;
            }
            if ($center && (int) $center->command_id === (int) $this->command_id) {
                return true;
            }
            if ($card->enrollment_command && $this->nisCommand) {
                return $card->enrollment_command === $this->nisCommand->name;
            }
            if ($card->distribution && $card->distribution->destination_command_id === $this->command_id) {
                return true;
            }
            return false;
        }

        return false;
    }

    public function canIssueCard(ResidenceCard $card): bool
    {
        if (! $this->hasRole(
            StaffRole::SuperAdmin,
            StaffRole::HeadquartersAdmin,
            StaffRole::Admin,
            StaffRole::ZonalAdmin,
            StaffRole::CommandAdmin,
            StaffRole::CardIssuanceOfficer,
            StaffRole::IssuingOfficer
        )) {
            return false;
        }

        if ($this->isHeadquarters()) {
            return true;
        }

        $card->loadMissing(['actualEnrollmentCenter', 'application.enrollmentCenter', 'distribution']);
        $dist = $card->distribution;

        if ($this->isCommand()) {
            // Must match the command of destination
            $targetCommandId = $dist?->destination_command_id
                ?? $card->actualEnrollmentCenter?->command_id
                ?? $card->application?->enrollmentCenter?->command_id;

            return (int) $targetCommandId === (int) $this->command_id;
        }

        if ($this->isZonal()) {
            $targetZoneId = $dist?->destination_zone_id
                ?? $card->actualEnrollmentCenter?->zone_id
                ?? $card->application?->enrollmentCenter?->zone_id;

            return (int) $targetZoneId === (int) $this->zone_id;
        }

        return false;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function auditLabel(): string
    {
        return "{$this->fullname} ({$this->service_number})";
    }

    public function zone(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NisZone::class, 'zone_id');
    }

    public function nisCommand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NisCommand::class, 'command_id');
    }

    public function enrollmentCenter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EnrollmentCenter::class, 'enrollment_center_id');
    }
}

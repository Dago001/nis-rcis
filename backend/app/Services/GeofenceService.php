<?php

namespace App\Services;

use App\Models\NisOfficeLocation;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Validation\ValidationException;

class GeofenceService
{
    /**
     * Calculate Haversine distance in meters between two GPS coordinates.
     */
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Verify whether a staff member's physical location complies with the authorized office geofence.
     *
     * @return array{verified: bool, exempt?: bool, override?: bool, office?: string, distance_meters?: float, radius_meters?: int, message: string}
     */
    public function verifyStaffLocation(
        ?User $user,
        ?float $latitude,
        ?float $longitude,
        string $action,
        ?int $commandId = null,
        ?int $centerId = null,
        ?string $overrideReason = null
    ): array {
        // Global toggle check
        if (! config('nis.geofencing_enabled', true)) {
            return [
                'verified' => true,
                'message' => 'Geofencing verification is disabled in current environment.',
            ];
        }

        // Exempt officer check
        if ($user && $user->geofence_exempt) {
            Audit::log('GEOFENCE_EXEMPT', "Geofence check bypassed (exempt officer): {$action}", actor: $user);

            return [
                'verified' => true,
                'exempt' => true,
                'message' => 'Officer is authorized for geofence exemption.',
            ];
        }

        // Authorized exception / override process
        if (! empty($overrideReason)) {
            Audit::log('GEOFENCE_OVERRIDE', "Geofence supervisor override applied for {$action}: {$overrideReason}", actor: $user, context: [
                'reason' => $overrideReason,
                'action' => $action,
            ]);

            return [
                'verified' => true,
                'override' => true,
                'message' => 'Authorized supervisor override recorded.',
            ];
        }

        // Resolve target authorized office location
        $office = null;
        if ($centerId) {
            $office = NisOfficeLocation::where('enrollment_center_id', $centerId)->where('is_active', true)->first();
        }
        if (! $office && $commandId) {
            $office = NisOfficeLocation::where('command_id', $commandId)->where('is_active', true)->first();
        }
        if (! $office && $user && $user->command_id) {
            $office = NisOfficeLocation::where('command_id', $user->command_id)->where('is_active', true)->first();
        }
        if (! $office && $user && $user->isHeadquarters()) {
            $office = NisOfficeLocation::where('code', 'NIS_HQ')->where('is_active', true)->first();
        }

        // If no specific office geofence configured for this command yet, allow with log
        if (! $office) {
            return [
                'verified' => true,
                'message' => 'No active geofence boundary is configured for this command/centre.',
            ];
        }

        // Check action-specific enforcement
        if ($action === 'BIOMETRICS' && ! $office->enforce_biometrics) {
            return ['verified' => true, 'message' => 'Geofencing not enforced for biometrics at this location.'];
        }
        if ($action === 'CARD_RECEIPT' && ! $office->enforce_card_receipt) {
            return ['verified' => true, 'message' => 'Geofencing not enforced for card receipt at this location.'];
        }
        if ($action === 'CARD_ISSUANCE' && ! $office->enforce_card_issuance) {
            return ['verified' => true, 'message' => 'Geofencing not enforced for card issuance at this location.'];
        }

        // If coordinates were not supplied
        if ($latitude === null || $longitude === null) {
            $strict = config('nis.geofencing_strict', false);
            if ($strict) {
                Audit::log('GEOFENCE_FAILED', "Geofence check failed: Location coordinates missing for {$action}", actor: $user);

                return [
                    'verified' => false,
                    'message' => "Physical Geofencing Required: You must enable browser location permissions or provide an authorized supervisor override to perform {$action}.",
                ];
            }

            Audit::log('GEOFENCE_SKIPPED', "Location coordinates unavailable for {$action} (non-strict mode)", actor: $user);

            return [
                'verified' => true,
                'message' => 'Location coordinates not provided; permitted under non-strict policy.',
            ];
        }

        // Calculate distance
        $distance = self::haversineDistance($latitude, $longitude, $office->latitude, $office->longitude);

        if ($distance <= $office->radius_meters) {
            Audit::log('GEOFENCE_VERIFIED', "Geofence verified for {$action} at {$office->name} (distance: ".round($distance)."m)", actor: $user, context: [
                'office_code' => $office->code,
                'distance_meters' => round($distance),
                'action' => $action,
            ]);

            return [
                'verified' => true,
                'office' => $office->name,
                'distance_meters' => round($distance, 1),
                'radius_meters' => $office->radius_meters,
                'message' => "Location verified within authorized perimeter of {$office->name}.",
            ];
        }

        Audit::log('GEOFENCE_FAILED', "Geofence boundary exceeded for {$action} at {$office->name} (distance: ".round($distance)."m, allowed: {$office->radius_meters}m)", actor: $user, context: [
            'office_code' => $office->code,
            'distance_meters' => round($distance),
            'allowed_radius' => $office->radius_meters,
            'action' => $action,
        ]);

        return [
            'verified' => false,
            'office' => $office->name,
            'distance_meters' => round($distance, 1),
            'radius_meters' => $office->radius_meters,
            'message' => "Physical Geofencing Verification Failed: You are currently ".round($distance)."m from the authorized office ({$office->name}), exceeding the permitted {$office->radius_meters}m boundary.",
        ];
    }

    /**
     * Assert location compliance, throwing ValidationException if out of bounds.
     */
    public function assertStaffLocation(
        ?User $user,
        ?float $latitude,
        ?float $longitude,
        string $action,
        ?int $commandId = null,
        ?int $centerId = null,
        ?string $overrideReason = null
    ): void {
        $result = $this->verifyStaffLocation($user, $latitude, $longitude, $action, $commandId, $centerId, $overrideReason);
        if (! $result['verified']) {
            throw ValidationException::withMessages([
                'geofence' => $result['message'],
            ]);
        }
    }
}

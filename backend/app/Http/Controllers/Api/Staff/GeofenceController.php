<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\NisOfficeLocation;
use App\Services\GeofenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    public function __construct(
        protected GeofenceService $geofenceService
    ) {}

    /**
     * List all active official NIS office locations and geofence perimeters.
     */
    public function locations(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = NisOfficeLocation::query()->where('is_active', true);

        // If user is command-scoped or zonal-scoped, filter or sort appropriately
        if ($user && $user->isCommand() && $user->command_id) {
            $query->where('command_id', $user->command_id);
        } elseif ($user && $user->isZonal() && $user->zone_id) {
            $query->where('zone_id', $user->zone_id);
        }

        $locations = $query->with(['zone', 'command'])->get()->map(function (NisOfficeLocation $loc) {
            return [
                'id' => $loc->id,
                'name' => $loc->name,
                'code' => $loc->code,
                'office_type' => $loc->office_type,
                'zone_id' => $loc->zone_id,
                'zone_name' => $loc->zone?->name,
                'command_id' => $loc->command_id,
                'command_name' => $loc->command?->name,
                'latitude' => (float) $loc->latitude,
                'longitude' => (float) $loc->longitude,
                'radius_meters' => (int) $loc->radius_meters,
                'address' => $loc->address,
                'enforce_biometrics' => (bool) $loc->enforce_biometrics,
                'enforce_card_issuance' => (bool) $loc->enforce_card_issuance,
                'enforce_card_receipt' => (bool) $loc->enforce_card_receipt,
            ];
        });

        return response()->json([
            'status' => 'success',
            'locations' => $locations,
            'geofencing_enabled' => (bool) config('nis.geofencing_enabled', true),
        ]);
    }

    /**
     * Get the active officer's designated office location.
     */
    public function myOffice(Request $request): JsonResponse
    {
        $user = $request->user();
        $targetOffice = $this->geofenceService->resolveOfficeForUser($user);

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'service_number' => $user->service_number,
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role,
                'is_headquarters' => $user->isHeadquarters(),
                'zone_id' => $user->zone_id,
                'zone_name' => $user->zone?->name,
                'command_id' => $user->command_id,
                'command_name' => $user->command?->name,
                'geofence_exempt' => (bool) $user->geofence_exempt,
            ],
            'office' => $targetOffice ? [
                'id' => $targetOffice->id,
                'name' => $targetOffice->name,
                'code' => $targetOffice->code,
                'office_type' => $targetOffice->office_type,
                'latitude' => (float) $targetOffice->latitude,
                'longitude' => (float) $targetOffice->longitude,
                'radius_meters' => (int) $targetOffice->radius_meters,
                'address' => $targetOffice->address,
            ] : null,
            'geofencing_enabled' => (bool) config('nis.geofencing_enabled', true),
        ]);
    }

    /**
     * Test/verify current coordinates against designated office.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'action' => 'nullable|string|in:BIOMETRICS,CARD_RECEIPT,CARD_ISSUANCE,LOGIN',
        ]);

        $user = $request->user();
        $action = $request->input('action', 'LOGIN');

        $result = $this->geofenceService->checkStaffLocation(
            officer: $user,
            latitude: (float) $request->input('latitude'),
            longitude: (float) $request->input('longitude'),
            action: $action
        );

        return response()->json([
            'status' => 'success',
            'verification' => $result,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\StaffRole;
use App\Models\NisCommand;
use App\Models\NisZone;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff account administration and user management.
 * Enforces role assignment hierarchies, 5-digit NIS service number validation,
 * geographical scoping (Headquarters, Zones, Commands), and geofence configuration.
 */
class UserController
{
    private const FIELDS = [
        'id', 'username', 'fullname', 'rank', 'service_number', 'email', 'role',
        'command', 'state', 'lga', 'zone_id', 'command_id', 'headquarters_unit',
        'geofence_exempt', 'is_active', 'must_change_password', 'last_login_at', 'created_at'
    ];

    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $query = User::with(['zone:id,name,code', 'command:id,name,code'])->orderBy('fullname');

        // Scoping based on jurisdiction
        if ($currentUser) {
            if ($currentUser->role === StaffRole::SuperAdmin || $currentUser->isHeadquarters()) {
                // National visibility across all zones and commands; optional command_id filter
                if ($request->filled('command_id')) {
                    $query->where('command_id', (int) $request->input('command_id'));
                }
            } elseif ($currentUser->isZonal() && $currentUser->zone_id) {
                // Zonal Admin sees accounts in their Zone
                $query->where(function ($q) use ($currentUser) {
                    $q->where('zone_id', $currentUser->zone_id)
                      ->orWhereHas('command', fn ($cq) => $cq->where('zone_id', $currentUser->zone_id));
                });
            } elseif ($currentUser->isCommand()) {
                // Command Admin sees accounts in their Command
                $cmdId = $currentUser->getEffectiveCommandId();
                if ($cmdId) {
                    $query->where('command_id', $cmdId);
                }
            }
        }

        // Subordinate admins cannot see SuperAdmin accounts
        if ($currentUser && $currentUser->role !== StaffRole::SuperAdmin) {
            $query->where('role', '!=', StaffRole::SuperAdmin->value);
        }

        $users = $query->get([...self::FIELDS, 'two_factor_confirmed_at'])->map(function (User $u) {
            return [
                ...$u->only(self::FIELDS),
                'role_label' => $u->role instanceof \BackedEnum ? $u->role->label() : (string) $u->role,
                'zone_name' => $u->zone?->name,
                'command_name' => $u->commandRel?->name ?? $u->command,
                'two_factor_enabled' => $u->two_factor_confirmed_at !== null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Determine which roles current user is allowed to assign
        $allowedRoles = StaffRole::cases();
        if ($currentUser) {
            if ($currentUser->role === StaffRole::SuperAdmin) {
                // All roles allowed
            } elseif ($currentUser->isHeadquarters()) {
                // HQ Admin cannot create SuperAdmin
                $allowedRoles = array_filter($allowedRoles, fn ($r) => $r !== StaffRole::SuperAdmin);
            } elseif ($currentUser->isZonal()) {
                // Zonal Admin can only create Zonal or Command roles within their zone
                $allowedRoles = array_filter($allowedRoles, fn ($r) => ! in_array($r, [
                    StaffRole::SuperAdmin,
                    StaffRole::HeadquartersAdmin,
                    StaffRole::CardProductionOfficer,
                    StaffRole::CardDistributionOfficer,
                ], true));
            } elseif ($currentUser->isCommand()) {
                // Command Admin can only create Command-level operational officers
                $allowedRoles = array_filter($allowedRoles, fn ($r) => in_array($r, [
                    StaffRole::VettingOfficer,
                    StaffRole::ApprovingOfficer,
                    StaffRole::BiometricsOfficer,
                    StaffRole::CardReceivingOfficer,
                    StaffRole::CardIssuanceOfficer,
                    StaffRole::ReportingAuditOfficer,
                    StaffRole::EnrollmentOfficer,
                    StaffRole::IssuingOfficer,
                ], true));
            }
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users'],
            'fullname' => ['required', 'string', 'max:150'],
            'rank' => ['nullable', 'string', 'max:50'],
            'service_number' => ['required', 'string', 'regex:/^(NIS-?)?[0-9]{5}$/i', 'unique:users'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::in(array_map(fn ($r) => $r->value, $allowedRoles))],
            'command' => ['nullable', 'string', 'max:150'],
            'command_id' => ['nullable', 'integer', 'exists:nis_commands,id'],
            'zone_id' => ['nullable', 'integer', 'exists:nis_zones,id'],
            'headquarters_unit' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
            'geofence_exempt' => ['nullable', 'boolean'],
        ]);

        // If current user is Command Admin, strictly force their command_id
        if ($currentUser && $currentUser->isCommand()) {
            $cmdId = $currentUser->getEffectiveCommandId();
            if ($cmdId) {
                $data['command_id'] = $cmdId;
            }
        }

        // Normalize service number format to NIS-XXXXX if entered as pure 5-digit number
        $cleanNumber = preg_replace('/[^0-9]/', '', $data['service_number']);
        $data['service_number'] = 'NIS-' . $cleanNumber;

        // Automatically associate Zone and State from Command if provided
        if (! empty($data['command_id'])) {
            $cmd = NisCommand::with('zone')->find($data['command_id']);
            if ($cmd) {
                $data['command'] = $cmd->name;
                $data['zone_id'] = $cmd->zone_id ?? $data['zone_id'] ?? null;
                $data['state'] = $cmd->state ?? $data['state'] ?? null;
            }
        } elseif (! empty($data['zone_id'])) {
            $zn = NisZone::find($data['zone_id']);
            if ($zn && empty($data['command'])) {
                $data['command'] = $zn->name;
            }
        }

        if (empty($data['command'])) {
            $data['command'] = ! empty($data['headquarters_unit'])
                ? "NIS Headquarters ({$data['headquarters_unit']})"
                : 'NIS Headquarters';
        }

        // Only SuperAdmin or HeadquartersAdmin can grant geofence exemption
        if (! empty($data['geofence_exempt'])) {
            if (! $currentUser || (! $currentUser->role === StaffRole::SuperAdmin && ! $currentUser->isHeadquarters())) {
                $data['geofence_exempt'] = false;
            }
        }

        $password = Str::password(16);
        $user = User::create([
            ...$data,
            'password' => $password,
            'must_change_password' => true,
        ]);

        Audit::log('STAFF_CREATED', "Created {$user->role->value} account {$user->auditLabel()} with service number {$user->service_number}", $user);

        // Send login credentials and temporary password to the officer's email
        try {
            $user->notify(new \App\Notifications\StaffTemporaryPassword($password, 'created'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to send temporary password email to staff {$user->email}: {$e->getMessage()}");
        }

        return response()->json([
            'status' => 'success',
            'data' => $user->only(self::FIELDS),
            'temporary_password' => $password,
            'message' => "Account created successfully. Login credentials and temporary password have been sent to {$user->email}.",
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($currentUser && $currentUser->role !== StaffRole::SuperAdmin && $user->role === StaffRole::SuperAdmin) {
            abort(403, 'Administrators cannot modify Super Administrator accounts.');
        }

        // Check jurisdiction permissions
        if ($currentUser && ! $currentUser->isHeadquarters() && $currentUser->role !== StaffRole::SuperAdmin) {
            if ($currentUser->isZonal() && $user->zone_id !== $currentUser->zone_id) {
                abort(403, 'You do not have jurisdiction over users outside your assigned Zone.');
            }
            if ($currentUser->isCommand() && $user->command_id !== $currentUser->command_id) {
                abort(403, 'You do not have jurisdiction over users outside your assigned Command.');
            }
        }

        $allowedRoles = StaffRole::cases();
        if ($currentUser && $currentUser->role !== StaffRole::SuperAdmin) {
            $allowedRoles = array_filter($allowedRoles, fn ($r) => $r !== StaffRole::SuperAdmin);
        }

        $data = $request->validate([
            'fullname' => ['sometimes', 'string', 'max:150'],
            'rank' => ['nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'service_number' => ['sometimes', 'string', 'regex:/^(NIS-?)?[0-9]{5}$/i', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(array_map(fn ($r) => $r->value, $allowedRoles))],
            'command' => ['sometimes', 'string', 'max:150'],
            'command_id' => ['nullable', 'integer', 'exists:nis_commands,id'],
            'zone_id' => ['nullable', 'integer', 'exists:nis_zones,id'],
            'headquarters_unit' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
            'geofence_exempt' => ['nullable', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_if($user->is($currentUser) && (($data['is_active'] ?? true) === false || isset($data['role'])), 422,
            'You cannot deactivate yourself or change your own role.');

        if (! empty($data['service_number'])) {
            $cleanNumber = preg_replace('/[^0-9]/', '', $data['service_number']);
            $data['service_number'] = 'NIS-' . $cleanNumber;
        }

        if (! empty($data['command_id'])) {
            $cmd = NisCommand::find($data['command_id']);
            if ($cmd) {
                $data['command'] = $cmd->name;
                $data['zone_id'] = $cmd->zone_id ?? $data['zone_id'] ?? $user->zone_id;
                $data['state'] = $cmd->state ?? $data['state'] ?? $user->state;
            }
        }

        // Restrict geofence exemption changes
        if (array_key_exists('geofence_exempt', $data)) {
            if (! $currentUser || (! $currentUser->role === StaffRole::SuperAdmin && ! $currentUser->isHeadquarters())) {
                unset($data['geofence_exempt']);
            }
        }

        $user->fill($data)->save();

        if (($user->wasChanged('is_active') && ! $user->is_active) || $user->wasChanged('role')) {
            $user->tokens()->update(['revoked' => true]);
        }

        Audit::log('STAFF_UPDATED', "Updated account {$user->auditLabel()}", $user, ['changes' => array_keys($user->getChanges())]);

        return response()->json([
            'status' => 'success',
            'data' => $user->only(self::FIELDS),
        ]);
    }

    public function resetTwoFactor(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);
        abort_if($user->is($currentUser), 422, 'Another Administrator must reset your own authenticator.');

        if ($currentUser && $currentUser->role !== StaffRole::SuperAdmin && $user->role === StaffRole::SuperAdmin) {
            abort(403, 'Administrators cannot modify Super Administrator accounts.');
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $user->tokens()->update(['revoked' => true]);
        Audit::log('STAFF_2FA_RESET', "Authenticator reset for {$user->auditLabel()}", $user);

        return response()->json(['message' => 'Authenticator reset. The officer will set it up again at the next sign-in.']);
    }

    public function revokeSessions(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($currentUser && $currentUser->role !== StaffRole::SuperAdmin && $user->role === StaffRole::SuperAdmin) {
            abort(403, 'Administrators cannot modify Super Administrator accounts.');
        }

        $count = $user->tokens()->where('revoked', false)->update(['revoked' => true]);
        Audit::log('STAFF_SESSIONS_REVOKED', "Signed {$user->auditLabel()} out of all sessions", $user, ['tokens' => $count]);

        return response()->json(['message' => "Signed out of {$count} session(s)."]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        abort_if($user->is($currentUser), 422, 'You cannot reset your own password here. Please use the change password setting.');

        if ($currentUser && $currentUser->role !== StaffRole::SuperAdmin && $user->role === StaffRole::SuperAdmin) {
            abort(403, 'Administrators cannot modify Super Administrator accounts.');
        }

        $password = Str::password(16);

        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();
        $user->tokens()->update(['revoked' => true]);
        Audit::log('STAFF_PASSWORD_RESET', "Temporary password issued for {$user->auditLabel()}", $user);

        // Send temporary password to the officer's email
        try {
            $user->notify(new \App\Notifications\StaffTemporaryPassword($password, 'reset'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to send password reset email to staff {$user->email}: {$e->getMessage()}");
        }

        return response()->json([
            'message' => "A new temporary password has been emailed to {$user->fullname} ({$user->email}).",
        ]);
    }
}

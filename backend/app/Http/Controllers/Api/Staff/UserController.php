<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\StaffRole;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff account administration (SuperAdmin only; legacy users.php).
 * New and reset accounts get a one-time temporary password.
 */
class UserController
{
    private const FIELDS = ['id', 'username', 'fullname', 'service_number', 'email', 'role', 'command', 'state', 'lga', 'is_active', 'must_change_password', 'last_login_at', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $query = User::orderBy('fullname');

        // Admin cannot see SuperAdmin accounts
        if ($currentUser && $currentUser->role === StaffRole::Admin) {
            $query->where('role', '!=', StaffRole::SuperAdmin->value);
        }

        return response()->json(['data' => $query->get([...self::FIELDS, 'two_factor_confirmed_at'])
            ->map(fn (User $u) => [...$u->only(self::FIELDS), 'two_factor_enabled' => $u->two_factor_confirmed_at !== null])]);
    }

    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Admin cannot add Super Administrator
        $allowedRoles = StaffRole::cases();
        if ($currentUser && $currentUser->role === StaffRole::Admin) {
            $allowedRoles = array_filter($allowedRoles, fn ($r) => $r !== StaffRole::SuperAdmin);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users'],
            'fullname' => ['required', 'string', 'max:150'],
            'service_number' => ['required', 'string', 'max:50', 'unique:users'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::in(array_map(fn ($r) => $r->value, $allowedRoles))],
            'command' => ['required', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
        ]);

        $this->validateQuota($data['role'], $data['state'] ?? null, $data['lga'] ?? null);

        $password = Str::password(16);
        $user = User::create([...$data, 'password' => $password, 'must_change_password' => true]);
        Audit::log('STAFF_CREATED', "Created {$user->role->value} account {$user->auditLabel()}", $user);

        // Send login credentials and temporary password to the officer's email
        try {
            $user->notify(new \App\Notifications\StaffTemporaryPassword($password, 'created'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to send temporary password email to staff {$user->email}: {$e->getMessage()}");
        }

        return response()->json([
            'data' => $user->only(self::FIELDS),
            'message' => "Account created successfully. Login credentials and temporary password have been sent to {$user->email}.",
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($currentUser && $currentUser->role === StaffRole::Admin && $user->role === StaffRole::SuperAdmin) {
            abort(403, 'Administrators cannot modify Super Administrator accounts.');
        }

        $allowedRoles = StaffRole::cases();
        if ($currentUser && $currentUser->role === StaffRole::Admin) {
            $allowedRoles = array_filter($allowedRoles, fn ($r) => $r !== StaffRole::SuperAdmin);
        }

        $data = $request->validate([
            'fullname' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(array_map(fn ($r) => $r->value, $allowedRoles))],
            'command' => ['sometimes', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_if($user->is($currentUser) && (($data['is_active'] ?? true) === false || isset($data['role'])), 422,
            'You cannot deactivate yourself or change your own role.');

        $targetRole = $data['role'] ?? $user->role->value;
        $targetState = array_key_exists('state', $data) ? $data['state'] : $user->state;
        $targetLga = array_key_exists('lga', $data) ? $data['lga'] : $user->lga;
        if (($data['is_active'] ?? $user->is_active) && ($targetState !== null || $targetLga !== null)) {
            $this->validateQuota($targetRole, $targetState, $targetLga, $user->id);
        }

        $user->fill($data)->save();

        // Deactivation or a role change ends every session, so new
        // permissions apply immediately.
        if (($user->wasChanged('is_active') && ! $user->is_active) || $user->wasChanged('role')) {
            $user->tokens()->update(['revoked' => true]);
        }

        Audit::log('STAFF_UPDATED', "Updated account {$user->auditLabel()}", $user, ['changes' => array_keys($user->getChanges())]);

        return response()->json(['data' => $user->only(self::FIELDS)]);
    }

    /**
     * Capacity verification:
     * - LGA level: max 5 Data Entry Officers and 5 Enrollment Officers per LGA.
     * - State level: max 1 Approving Officer per State.
     * - Headquarters level: allowed for Data Entry, Approving, and Enrollment Officers without local LGA restriction.
     */
    private function validateQuota(string $role, ?string $state, ?string $lga, ?int $ignoreUserId = null): void
    {
        // If assigned to Headquarters or command has no specific state/lga, skip LGA quotas
        if (empty($state) && empty($lga)) {
            return;
        }

        if (! empty($state) && ! empty($lga)) {
            if ($role === StaffRole::DataEntryOfficer->value) {
                $query = User::where('role', StaffRole::DataEntryOfficer->value)
                    ->where('state', $state)
                    ->where('lga', $lga)
                    ->where('is_active', true);
                if ($ignoreUserId) {
                    $query->where('id', '!=', $ignoreUserId);
                }
                if ($query->count() >= 5) {
                    abort(422, "Maximum capacity reached: {$lga} LGA ({$state} State) already has 5 active Data Entry Officers.");
                }
            } elseif ($role === StaffRole::EnrollmentOfficer->value) {
                $query = User::where('role', StaffRole::EnrollmentOfficer->value)
                    ->where('state', $state)
                    ->where('lga', $lga)
                    ->where('is_active', true);
                if ($ignoreUserId) {
                    $query->where('id', '!=', $ignoreUserId);
                }
                if ($query->count() >= 5) {
                    abort(422, "Maximum capacity reached: {$lga} LGA ({$state} State) already has 5 active Enrollment Officers.");
                }
            }
        }

        if (! empty($state) && $role === StaffRole::ApprovingOfficer->value) {
            $query = User::where('role', StaffRole::ApprovingOfficer->value)
                ->where('state', $state)
                ->where('is_active', true);
            if ($ignoreUserId) {
                $query->where('id', '!=', $ignoreUserId);
            }
            if ($query->count() >= 1) {
                abort(422, "Maximum capacity reached: {$state} State already has 1 active Approving Officer.");
            }
        }
    }

    /**
     * Clear an officer's authenticator (lost or new phone). They set it up
     * again at their next sign-in. All their sessions are ended.
     */
    public function resetTwoFactor(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);
        abort_if($user->is($currentUser), 422, 'Another Super Administrator must reset your own authenticator.');

        if ($currentUser && $currentUser->role === StaffRole::Admin && $user->role === StaffRole::SuperAdmin) {
            abort(403, 'Administrators cannot modify Super Administrator accounts.');
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $user->tokens()->update(['revoked' => true]);
        Audit::log('STAFF_2FA_RESET', "Authenticator reset for {$user->auditLabel()}", $user);

        return response()->json(['message' => 'Authenticator reset. The officer will set it up again at the next sign-in.']);
    }

    /** Sign an officer out everywhere (revokes every access and refresh token). */
    public function revokeSessions(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($currentUser && $currentUser->role === StaffRole::Admin && $user->role === StaffRole::SuperAdmin) {
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

        if ($currentUser && $currentUser->role === StaffRole::Admin && $user->role === StaffRole::SuperAdmin) {
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

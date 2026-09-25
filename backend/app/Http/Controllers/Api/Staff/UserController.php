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
    private const FIELDS = ['id', 'username', 'fullname', 'service_number', 'email', 'role', 'command', 'is_active', 'must_change_password', 'last_login_at', 'created_at'];

    public function index(): JsonResponse
    {
        return response()->json(['data' => User::orderBy('fullname')->get(self::FIELDS)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users'],
            'fullname' => ['required', 'string', 'max:150'],
            'service_number' => ['required', 'string', 'max:50', 'unique:users'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'command' => ['required', 'string', 'max:150'],
        ]);

        $password = Str::password(16);
        $user = User::create([...$data, 'password' => $password, 'must_change_password' => true]);
        Audit::log('STAFF_CREATED', "Created {$user->role->value} account {$user->auditLabel()}", $user);

        return response()->json(['data' => $user->only(self::FIELDS), 'temporary_password' => $password], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'fullname' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', Rule::enum(StaffRole::class)],
            'command' => ['sometimes', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_if($user->is($request->user()) && (($data['is_active'] ?? true) === false || isset($data['role'])), 422,
            'You cannot deactivate yourself or change your own role.');

        $user->fill($data)->save();

        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $user->tokens()->update(['revoked' => true]);
        }

        Audit::log('STAFF_UPDATED', "Updated account {$user->auditLabel()}", $user, ['changes' => array_keys($user->getChanges())]);

        return response()->json(['data' => $user->only(self::FIELDS)]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $password = Str::password(16);

        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();
        $user->tokens()->update(['revoked' => true]);
        Audit::log('STAFF_PASSWORD_RESET', "Temporary password issued for {$user->auditLabel()}", $user);

        return response()->json(['temporary_password' => $password]);
    }
}

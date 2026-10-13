<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $applicant = $request->user('applicant');
        $staff = $request->user('staff');

        return [
            ...parent::share($request),
            'auth' => [
                'applicant' => $applicant ? [
                    'id' => $applicant->id,
                    'name' => $applicant->fullName(),
                    'email' => $applicant->email,
                    'email_verified_at' => $applicant->email_verified_at,
                    'phone' => $applicant->phone,
                    'nationality' => $applicant->nationality,
                    'passport_number' => $applicant->passport_number,
                ] : null,
                'staff' => $staff ? [
                    'id' => $staff->id,
                    'name' => $staff->fullname,
                    'service_number' => $staff->service_number,
                    'role' => $staff->role->value,
                    'role_label' => $staff->role->label(),
                    'state' => $staff->state,
                    'lga' => $staff->lga,
                    'command' => $staff->command,
                    'must_change_password' => $staff->must_change_password,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}

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
        $cookieLang = $request->cookie('nis_lang');
        if (in_array($cookieLang, ['en', 'fr'], true)) {
            app()->setLocale($cookieLang);
        }

        $applicant = $request->user('applicant');
        $staff = $request->user('staff');

        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'auth' => [
                'applicant' => $applicant ? [
                    'id' => $applicant->id,
                    'name' => $applicant->fullName(),
                    'email' => $applicant->email,
                    'email_verified_at' => $applicant->email_verified_at,
                    'phone' => $applicant->phone,
                    'nationality' => $applicant->getAttributes()['nationality'] ?? null,
                    'passport_number' => $applicant->getAttributes()['passport_number'] ?? null,
                ] : null,
                'staff' => $staff ? [
                    'id' => $staff->id,
                    'name' => $staff->fullname,
                    'service_number' => $staff->service_number,
                    'role' => $staff->role->value,
                    'role_label' => $staff->role->label(),
                    'state' => $staff->getAttributes()['state'] ?? null,
                    'lga' => $staff->getAttributes()['lga'] ?? null,
                    'command' => $staff->getAttributes()['command'] ?? null,
                    'must_change_password' => (bool) ($staff->getAttributes()['must_change_password'] ?? false),
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

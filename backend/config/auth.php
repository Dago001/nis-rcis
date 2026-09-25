<?php

use App\Models\Applicant;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| NIS-RCIS authentication
|--------------------------------------------------------------------------
|
| Two completely separate identities exist in this system:
|
|  - Staff (NIS officers)  -> provider "users"
|  - Applicants (public)   -> provider "applicants"
|
| Each identity has a *session* guard, used only on the OAuth2 authorization
| server login pages, and a *passport* guard, used by the REST API. OAuth
| clients are bound to exactly one provider (oauth_clients.provider), so a
| token issued to the applicant portal can never authenticate as staff.
|
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'staff'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'applicants'),
    ],

    'guards' => [
        // Session guards (authorization server login pages only)
        'staff' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'applicant' => [
            'driver' => 'session',
            'provider' => 'applicants',
        ],

        // OAuth2 bearer token guards (REST API)
        'api' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],
        'applicant-api' => [
            'driver' => 'passport',
            'provider' => 'applicants',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
        'applicants' => [
            'driver' => 'eloquent',
            'model' => Applicant::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 30,
            'throttle' => 60,
        ],
        'applicants' => [
            'provider' => 'applicants',
            'table' => 'applicant_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];

<?php

return [

    'organization' => 'Nigeria Immigration Service',
    'directorate' => 'Directorate of Visa and Residency',
    'issuing_country' => 'FEDERAL REPUBLIC OF NIGERIA',
    'approving_authority' => 'COMPTROLLER GENERAL OF IMMIGRATION',

    /*
    | Public URLs of the Next.js frontend. Used for links in e-mails
    | (verification, password reset, application notifications) and as
    | the "create account" target on the applicant login page.
    */
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    /*
    | Residence card fee and validity.
    */
    'fee_naira' => (int) env('RESIDENCE_CARD_FEE_NAIRA', 35000),
    'card_validity_years' => (int) env('CARD_VALIDITY_YEARS', 2),

    /*
    | Local testing only: let applicants sign in without clicking the e-mail
    | verification link. On by default when APP_ENV=local; always ignored in
    | production (see App\Support\Features::skipEmailVerification()).
    */
    'skip_email_verification' => (bool) env('SKIP_EMAIL_VERIFICATION', env('APP_ENV') === 'local'),

    /*
    | Private document storage. "s3" in production (any S3-compatible
    | service); "local" is a private, non-web-served disk for development.
    */
    'documents_disk' => env('DOCUMENTS_DISK', 's3'),
    'document_url_ttl_minutes' => 10,
    'max_upload_kb' => 2048,

    /*
    | Optional ClamAV daemon for upload scanning, e.g.
    | unix:///var/run/clamav/clamd.ctl or tcp://127.0.0.1:3310.
    | When set, uploads are refused if the scanner cannot be reached.
    */
    'clamav_socket' => env('CLAMAV_SOCKET'),

    /*
    | Paystack. Payments are only trusted after server-side verification
    | (transaction verify API or signed webhook). The secret key never
    | leaves the backend.
    */
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        // Development only: simulate successful payments. Ignored in production.
        'fake' => (bool) env('PAYMENTS_FAKE', false),
    ],

    /*
    | Chat assistant. Without an API key it answers from the built-in
    | knowledge base only. The key never leaves the backend.
    */
    'assistant' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ASSISTANT_MODEL', 'claude-opus-5'),
        'effort' => env('ASSISTANT_EFFORT', 'low'),
    ],

    /*
    | OAuth2 scopes. Each OAuth client row carries the subset it may request.
    */
    'scopes' => [
        'applicant' => 'Applicant portal: manage your own residence card applications',
        'staff' => 'Staff console: NIS officer operations (further limited by role)',
        'cards:verify' => 'Partner integration: verify residence card validity',
    ],

];

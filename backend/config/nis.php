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

    // Service-level target: decide an application within this many working
    // days of submission. Public holidays (YYYY-MM-DD) are not working days.
    'sla_working_days' => (int) env('SLA_WORKING_DAYS', 10),
    'public_holidays' => array_filter(explode(',', (string) env('PUBLIC_HOLIDAYS', ''))),

    // Connections to other government systems (need formal agreements).
    // driver: http (live), simulated (testing only; refused in production)
    // or none. Until configured, checks are recorded as "not checked".
    'integrations' => [
        'interpol_sltd' => [
            'driver' => env('INTERPOL_SLTD_DRIVER', 'simulated'),
            'url' => env('INTERPOL_SLTD_URL'),
            'api_key' => env('INTERPOL_SLTD_API_KEY'),
            'timeout' => (int) env('INTERPOL_SLTD_TIMEOUT', 10),
            'test_hits' => array_filter(explode(',', (string) env('INTERPOL_SLTD_TEST_HITS', ''))),
        ],
        'moi_quota' => [
            'driver' => env('MOI_QUOTA_DRIVER', 'simulated'),
            'url' => env('MOI_QUOTA_URL'),
            'api_key' => env('MOI_QUOTA_API_KEY'),
            'timeout' => (int) env('MOI_QUOTA_TIMEOUT', 10),
        ],
    ],

    // Require fingerprints from the scanner at the biometrics desk (at least
    // the two index fingers). Leave off until scanners are installed.
    'fingerprints_required' => (bool) env('FINGERPRINTS_REQUIRED', false),

    // Monitoring: who is e-mailed when a health check fails or a new
    // server error appears (comma-separated addresses).
    'alerts' => [
        'emails' => array_filter(array_map('trim', explode(',', (string) env('ALERT_EMAILS', '')))),
        'backup_max_age_hours' => (int) env('ALERT_BACKUP_MAX_AGE_HOURS', 36),
        'disk_min_free_percent' => (int) env('ALERT_DISK_MIN_FREE_PERCENT', 10),
        'disk_min_free_gb' => (int) env('ALERT_DISK_MIN_FREE_GB', 20),
    ],

    // Shown on every page of a non-live copy, e.g. "STAGING". Empty on live.
    'environment_label' => env('ENVIRONMENT_LABEL', ''),

    // Web push for the installable applicant portal (php artisan nis:vapid-keys).
    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:support@immigration.gov.ng'),
    ],

    // Warn when fewer blank cards than this are left in stock.
    'card_stock_low' => (int) env('CARD_STOCK_LOW', 100),

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
    | Automatic passport photograph checks (size, exposure, sharpness,
    | background) on applicant uploads.
    */
    'photo_quality_check' => (bool) env('PHOTO_QUALITY_CHECK', true),
    'photo_min_sharpness' => (float) env('PHOTO_MIN_SHARPNESS', 25),

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
    | Nigeria Data Protection Act 2023: privacy notice version shown at
    | registration, and how long personal data is kept (days).
    */
    'privacy_policy_version' => '2026-09',
    'retention' => [
        'abandoned_drafts_days' => (int) env('RETENTION_DRAFT_DAYS', 90),
        'unverified_accounts_days' => (int) env('RETENTION_UNVERIFIED_DAYS', 30),
        'rejected_documents_days' => (int) env('RETENTION_REJECTED_DOCS_DAYS', 730),
        'unpaid_payments_days' => (int) env('RETENTION_UNPAID_PAYMENT_DAYS', 30),
    ],

    /*
    | Encrypted backups (php artisan nis:backup). BACKUP_KEY is a separate
    | base64 key (php artisan nis:backup --generate-key); keep a copy of it
    | somewhere safe: backups cannot be restored without it.
    */
    'backup' => [
        'key' => env('BACKUP_KEY'),
        'path' => env('BACKUP_PATH', storage_path('backups')),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'pg_dump' => env('PG_DUMP_PATH', 'pg_dump'),
    ],

    /*
    | Staff two-factor sign-in (authenticator app codes). Always required in
    | production; STAFF_TWO_FACTOR=false only disables it for local testing.
    */
    'staff_two_factor' => (bool) env('STAFF_TWO_FACTOR', true),

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

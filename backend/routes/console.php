<?php

use Illuminate\Support\Facades\Schedule;

// Remove revoked and expired OAuth2 tokens and auth codes.
Schedule::command('passport:purge')->daily();

// Clear old password reset tokens.
Schedule::command('auth:clear-resets applicants')->everyFifteenMinutes();
Schedule::command('auth:clear-resets users')->everyFifteenMinutes();

// Nigeria Data Protection Act: delete personal data past its retention period.
Schedule::command('nis:retention')->dailyAt('02:00');

// Encrypted nightly backup (only when BACKUP_KEY is configured).
Schedule::command('nis:backup')->dailyAt('01:30')->when(fn () => filled(config('nis.backup.key')));

// E-mail reminders: biometrics appointments (day before) and card expiry (90/30/7 days).
Schedule::command('nis:reminders')->dailyAt('07:00');

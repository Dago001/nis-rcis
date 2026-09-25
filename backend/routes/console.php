<?php

use Illuminate\Support\Facades\Schedule;

// Remove revoked and expired OAuth2 tokens and auth codes.
Schedule::command('passport:purge')->daily();

// Clear old password reset tokens.
Schedule::command('auth:clear-resets applicants')->everyFifteenMinutes();
Schedule::command('auth:clear-resets users')->everyFifteenMinutes();

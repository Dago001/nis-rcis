<?php

use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;

/*
| OAuth2 token endpoint. Called server-to-server (Next.js BFF, partner
| systems), authenticated by client credentials, so it must NOT run the
| session/CSRF "web" middleware group.
*/
Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle:oauth-token')
    ->name('passport.token');

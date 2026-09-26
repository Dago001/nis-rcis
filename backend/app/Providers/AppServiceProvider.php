<?php

namespace App\Providers;

use App\Assistant\ClaudeLanguageModel;
use App\Assistant\LanguageModel;
use App\Models\OAuthClient;
use App\OAuth\StrictScopeRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // OAuth routes are registered explicitly in routes/web.php so the
        // authorize endpoint can pick the staff or applicant login guard.
        Passport::ignoreRoutes();

        $this->app->bind(ScopeRepository::class, StrictScopeRepository::class);
        $this->app->bind(LanguageModel::class, ClaudeLanguageModel::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // The API is called server-to-server by Next.js on an internal
        // address; links in e-mails must always use the public APP_URL.
        URL::forceRootUrl(config('app.url'));
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureOAuth();
        $this->configureRateLimiting();
    }

    private function configureOAuth(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::tokensCan(config('nis.scopes'));

        // Short-lived access tokens; the Next.js BFF refreshes silently.
        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addHours(12));
        Passport::clientCredentialsTokensExpireIn(now()->addMinutes(30));

        // Consent screen for third-party authorization-code clients.
        // Our own first-party portals skip it (OAuthClient::skipsAuthorization).
        Passport::authorizationView('oauth.authorize');
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier().'|'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('identifier')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Authenticator codes: 5 tries a minute per pending sign-in.
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by('2fa|'.($request->session()->get('staff_2fa')['id'] ?? $request->ip())));

        RateLimiter::for('oauth-token', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Public, unauthenticated lookups (tracking, card verification)
        RateLimiter::for('public-lookup', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Chat assistant: generous for people, useless for scripted abuse.
        RateLimiter::for('assistant', fn (Request $request) => [
            Limit::perMinute(12)->by('assistant-min|'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perDay(300)->by('assistant-day|'.$request->ip()),
        ]);

        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
    }
}

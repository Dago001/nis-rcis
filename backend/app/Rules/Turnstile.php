<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Turnstile implements ValidationRule
{
    /**
     * Get the validation rule array based on whether Turnstile is configured.
     *
     * @return array<int, mixed>
     */
    public static function rule(): array
    {
        if (! config('services.turnstile.enabled') || empty(config('services.turnstile.secret_key'))) {
            return ['nullable'];
        }

        return ['required', 'string', new self()];
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secretKey = config('services.turnstile.secret_key');

        if (! config('services.turnstile.enabled') || empty($secretKey)) {
            return;
        }

        if (empty($value) || ! is_string($value)) {
            $fail('Please complete the security check.');
            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(6)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secretKey,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            if (! $response->successful() || ! ($response->json('success') === true)) {
                Log::warning('Cloudflare Turnstile verification failed', [
                    'ip' => request()->ip(),
                    'error_codes' => $response->json('error-codes'),
                ]);

                $fail('Security check failed. Please refresh and try again.');
            }
        } catch (\Throwable $e) {
            Log::error('Cloudflare Turnstile connection error: ' . $e->getMessage());
            $fail('Unable to verify security check. Please try again.');
        }
    }
}

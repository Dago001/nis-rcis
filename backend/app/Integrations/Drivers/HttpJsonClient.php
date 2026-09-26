<?php

namespace App\Integrations\Drivers;

use App\Integrations\CheckResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared JSON-over-HTTPS client for the government adapters. The exact
 * request and response fields are mapped in each adapter once the
 * interface specification is received from the agency.
 */
abstract class HttpJsonClient
{
    public function __construct(protected readonly array $config) {}

    protected function post(string $path, array $body, callable $map): CheckResult
    {
        try {
            $response = Http::baseUrl((string) $this->config['url'])
                ->withToken((string) $this->config['api_key'])
                ->acceptJson()->asJson()
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->retry(2, 500, throw: false)
                ->post($path, $body);

            if (! $response->successful()) {
                return $this->failure("HTTP {$response->status()}", $response);
            }

            return $map($response->json() ?? []);
        } catch (Throwable $e) {
            Log::warning(static::class.' failed: '.$e->getMessage());

            return new CheckResult(CheckResult::ERROR, 'The service could not be reached. Try again later.');
        }
    }

    private function failure(string $why, Response $response): CheckResult
    {
        Log::warning(static::class." returned {$why}", ['body' => mb_substr($response->body(), 0, 500)]);

        return new CheckResult(CheckResult::ERROR, "The service answered with an error ({$why}).");
    }
}

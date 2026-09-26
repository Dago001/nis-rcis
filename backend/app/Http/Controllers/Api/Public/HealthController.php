<?php

namespace App\Http\Controllers\Api\Public;

use App\Services\SystemHealth;
use Illuminate\Http\JsonResponse;

/**
 * For external uptime monitors: overall status only, never details.
 * 200 while working (ok or warn), 503 when a check fails.
 */
class HealthController
{
    public function __invoke(SystemHealth $health): JsonResponse
    {
        $status = $health->overall($health->checks());

        return response()->json(['status' => $status, 'time' => now()->toIso8601String()], $status === SystemHealth::FAIL ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }
}

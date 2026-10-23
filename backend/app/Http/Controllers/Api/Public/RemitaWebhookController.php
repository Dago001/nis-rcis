<?php

namespace App\Http\Controllers\Api\Public;

use App\Services\RemitaGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RemitaWebhookController
{
    public function __invoke(Request $request, RemitaGateway $gateway): Response
    {
        $signature = $request->header('x-remita-signature') ?? $request->header('Authorization');
        $valid = $gateway->handleWebhook((string) $request->getContent(), $signature);

        return response()->noContent($valid ? 200 : 400);
    }
}

<?php

namespace App\Http\Controllers\Api\Public;

use App\Services\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController
{
    public function __invoke(Request $request, PaystackGateway $gateway): Response
    {
        $valid = $gateway->handleWebhook($request->getContent(), $request->header('x-paystack-signature'));

        return response()->noContent($valid ? 200 : 401);
    }
}

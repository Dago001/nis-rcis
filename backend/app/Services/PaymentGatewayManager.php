<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

/**
 * Payment Gateway Manager.
 *
 * Directs transactions to either Remita (official Federal Government TSA portal)
 * or Paystack based on application configuration and payment provider records.
 */
class PaymentGatewayManager
{
    public function __construct(
        private RemitaGateway $remita,
        private PaystackGateway $paystack,
    ) {}

    /**
     * Resolve the active or specific gateway instance.
     */
    public function resolve(?string $provider = null): PaymentGatewayInterface
    {
        $gatewayKey = strtolower(trim((string) ($provider ?: config('nis.payment_gateway', 'remita'))));

        return match ($gatewayKey) {
            'remita', 'remita_tsa', 'tsa' => $this->remita,
            'paystack' => $this->paystack,
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gatewayKey}"),
        };
    }

    /**
     * Resolve the gateway used for an existing payment record.
     */
    public function forPayment(Payment $payment): PaymentGatewayInterface
    {
        if ($payment->provider) {
            return $this->resolve($payment->provider);
        }

        // Infer from gateway_response if provider was not recorded
        $response = $payment->gateway_response ?? [];
        if (isset($response['RRR']) || isset($response['rrr']) || str_starts_with((string) $payment->channel, 'remita')) {
            return $this->remita;
        }

        return $this->resolve('paystack');
    }

    /**
     * Format receipt data for a payment according to its provider.
     */
    public static function receipt(Payment $payment): array
    {
        $provider = strtoupper(trim((string) $payment->provider));
        $response = $payment->gateway_response ?? [];

        if ($provider === 'REMITA' || isset($response['RRR']) || isset($response['rrr']) || str_starts_with((string) $payment->channel, 'remita')) {
            return RemitaGateway::receipt($payment);
        }

        return PaystackGateway::receipt($payment);
    }
}

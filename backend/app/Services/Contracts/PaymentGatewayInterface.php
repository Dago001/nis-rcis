<?php

namespace App\Services\Contracts;

use App\Models\Applicant;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Whether simulated testing mode is active.
     */
    public function isFake(): bool;

    /**
     * Initialize payment transaction and return checkout details.
     *
     * @return array{
     *     payment: Payment,
     *     authorization_url: ?string,
     *     access_code: ?string,
     *     public_key: ?string,
     *     rrr: ?string,
     *     gateway: string,
     *     fake: bool
     * }
     */
    public function initialize(Applicant $applicant, int $amountKobo, string $callbackUrl): array;

    /**
     * Confirm the payment with the gateway and record outcome.
     */
    public function verify(Payment $payment): Payment;

    /**
     * Refund a confirmed payment in full.
     *
     * @return array{ok: bool, reference: ?string, message: string}
     */
    public function refund(Payment $payment): array;

    /**
     * Format receipt details for display and PDF generation.
     *
     * @return array{date: ?string, gateway: string, mode: ?string, gateway_reference: ?string, pan: ?string}
     */
    public static function receipt(Payment $payment): array;

    /**
     * Handle incoming webhook notification.
     */
    public function handleWebhook(string $rawBody, ?string $signature): bool;
}

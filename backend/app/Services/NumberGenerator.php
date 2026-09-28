<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Collision-free identifiers backed by PostgreSQL sequences.
 */
class NumberGenerator
{
    /** e.g. RC-2026-100001 */
    public function applicationNumber(): string
    {
        $next = DB::scalar("SELECT nextval('application_number_seq')");

        return sprintf('RC-%s-%06d', now()->format('Y'), $next);
    }

    /** Unguessable reference printed on slips (needed for public tracking). */
    public function referenceNumber(): string
    {
        return 'NIS-'.strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
    }

    /** e.g. 389108 (continues the legacy series) */
    public function cardNumber(): string
    {
        return (string) DB::scalar("SELECT nextval('card_number_seq')");
    }

    /** Legacy booklet format: RC-389108/26 */
    public function bookletNumber(string $cardNumber): string
    {
        return "RC-{$cardNumber}/".now()->format('y');
    }

    public function verificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function paymentReference(): string
    {
        return 'NISRC-'.now()->format('ymd').'-'.strtoupper(Str::random(10));
    }
}

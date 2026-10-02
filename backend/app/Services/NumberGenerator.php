<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Collision-free identifiers backed by PostgreSQL sequences.
 */
class NumberGenerator
{
    private function nextVal(string $seqName, int $defaultStart): int
    {
        if (DB::getDriverName() === 'pgsql') {
            return (int) DB::scalar("SELECT nextval('{$seqName}')");
        }

        // MySQL sequence simulation via atomic update
        return DB::transaction(function () use ($seqName, $defaultStart) {
            DB::table('system_sequences')->insertOrIgnore([
                'name' => $seqName,
                'current_val' => $defaultStart - 1,
            ]);

            DB::table('system_sequences')
                ->where('name', $seqName)
                ->increment('current_val', 1);

            return (int) DB::table('system_sequences')
                ->where('name', $seqName)
                ->value('current_val');
        });
    }

    /** e.g. RC-2026-100001 */
    public function applicationNumber(): string
    {
        $next = $this->nextVal('application_number_seq', 100001);

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
        return (string) $this->nextVal('card_number_seq', 389108);
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

<?php

namespace App\Integrations;

/** The outcome of a check against another government system. */
final class CheckResult
{
    public const CLEAR = 'CLEAR';           // nothing found (SLTD)

    public const HIT = 'HIT';               // the document is recorded as stolen or lost (SLTD)

    public const VALID = 'VALID';           // record found and in order (quota)

    public const INVALID = 'INVALID';       // record found but not in order: expired, exhausted, other employer (quota)

    public const NOT_FOUND = 'NOT_FOUND';   // no such record (quota)

    public const ERROR = 'ERROR';           // the service could not be reached or answered badly

    public const DISABLED = 'DISABLED';     // no connection configured

    public function __construct(
        public readonly string $status,
        public readonly string $summary,
        public readonly ?string $reference = null,
        public readonly array $details = [],
    ) {}
}

<?php

namespace App\Support;

/**
 * Escape user text used inside a LIKE / ILIKE pattern.
 *
 * Values are always passed as bound parameters (never concatenated into
 * SQL), so this is not about injection: it stops "%" and "_" in a search box
 * from turning into wildcards that scan or enumerate the whole table.
 */
class Like
{
    public static function contains(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}

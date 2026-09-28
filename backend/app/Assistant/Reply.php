<?php

namespace App\Assistant;

/**
 * One assistant answer, as sent to the chat widget.
 */
final class Reply
{
    /**
     * @param  'knowledge'|'ai'|'personal'|'guard'|'fallback'  $source
     * @param  list<array{label: string, href: string}>  $links
     */
    public function __construct(
        public readonly string $text,
        public readonly string $source,
        public readonly array $links = [],
    ) {}
}

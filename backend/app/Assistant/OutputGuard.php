<?php

namespace App\Assistant;

/**
 * Last line of defence on everything the language model says.
 *
 * The model is never given database content, but its reply is still
 * scrubbed before it reaches the browser: markup is removed, only links to
 * our own pages survive, and anything shaped like an identifier, secret,
 * query or server path is redacted.
 */
class OutputGuard
{
    public const MAX_LENGTH = 1500;

    public function __construct(private readonly KnowledgeBase $knowledge) {}

    public function clean(string $reply): string
    {
        // Plain text only: the widget renders text, never HTML.
        $reply = strip_tags($reply);
        $reply = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $reply) ?? '';

        // Markdown links: keep the label, keep the target only if it is ours.
        $reply = preg_replace_callback('/\[([^\]]{1,80})\]\(([^)\s]{1,200})\)/', fn ($m) => $this->isAllowedPath($m[2]) ? "{$m[1]} ({$m[2]})" : $m[1], $reply) ?? '';

        $reply = preg_replace([
            // External URLs and e-mail addresses (phishing / exfiltration).
            '#\b(?:https?|ftp|file|data|javascript)://?\S+#i',
            '#\bwww\.\S+#i',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            // Secrets and keys.
            '/\b(sk-ant-|sk-|pk_live_|sk_live_|pk_test_|sk_test_)[\w-]{6,}/i',
            '/-----BEGIN [A-Z ]+-----[\s\S]*?(-----END [A-Z ]+-----|$)/',
            '/\b[A-Za-z0-9+\/]{40,}={0,2}/',
            // Identifiers: long digit runs (phones, card or account numbers) and passport-like codes.
            '/\+?\d[\d\s-]{7,}\d/',
            '/\b[A-Z]{1,2}\d{6,9}\b/',
            // SQL, server paths and stack traces.
            '/\b(select\s.+?\sfrom|insert\s+into|update\s+\w+\s+set|delete\s+from|drop\s+table)\b[^.\n]*/i',
            '#(/(?:home|var|etc|usr|srv|opt|root|tmp)/\S+|[A-Z]:\\\\\S+)#i',
            '/\b(SQLSTATE|Stack trace|#\d+ \S+\.php|vendor\/\S+)\S*/i',
        ], '[removed]', $reply) ?? '';

        $reply = trim(preg_replace("/[ \t]+/", ' ', $reply) ?? '');

        return mb_strlen($reply) > self::MAX_LENGTH ? mb_substr($reply, 0, self::MAX_LENGTH - 1).'…' : $reply;
    }

    /**
     * Internal links found in a reply, for the widget to render as buttons.
     *
     * @return list<array{label: string, href: string}>
     */
    public function links(string $reply): array
    {
        $links = [];
        foreach ($this->knowledge->entries() as $entry) {
            foreach ($entry['links'] as $link) {
                if (str_contains($reply, $link['href'].' ') || str_contains($reply, $link['href'].')') || str_ends_with($reply, $link['href'])) {
                    $links[$link['href']] = $link;
                }
            }
        }

        return array_values($links);
    }

    public function isAllowedPath(string $href): bool
    {
        return in_array($href, $this->knowledge->allowedPaths(), true);
    }
}

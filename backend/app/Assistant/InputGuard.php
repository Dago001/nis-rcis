<?php

namespace App\Assistant;

/**
 * First line of defence for chat messages.
 *
 * The assistant never builds SQL, never runs code and never reads the
 * database from user text, so none of these payloads could work. They are
 * still refused early (and logged) so probing gets a flat answer, costs no
 * model call, and shows up in the security log.
 */
class InputGuard
{
    public const MAX_LENGTH = 500;

    /** @var array<string, list<string>> */
    private const PATTERNS = [
        'sql' => [
            '/\bunion\b(\s+all)?\s+select\b/i',
            '/\bselect\s+(\*|[\w.]+\s*,\s*[\w.,\s]+|count\s*\(|\w+\s*\()\s*.*\bfrom\b/i',
            '/\bselect\b.{1,60}\bfrom\s+[\w.]+\s+(where|limit|order|group|join)\b/i',
            '/\b(drop|truncate|alter)\s+(table|database|schema|index|column)\b/i',
            '/\bdelete\s+from\s+\w+/i',
            '/\binsert\s+into\s+\w+/i',
            '/\bupdate\s+\w+\s+set\s+\w+\s*=/i',
            "/('|\")\s*(or|and)\s*('|\"|\d)[^\n]{0,20}=/i",
            '/(\'|")\s*(--|#|\/\*)/',
            '/;\s*(drop|delete|shutdown|exec|select|update|insert)\b/i',
            '/\b(information_schema|pg_catalog|pg_sleep|xp_cmdshell|waitfor\s+delay)\b|\b(sleep|benchmark)\s*\(\s*\d/i',
        ],
        'script' => [
            '/<\s*\/?\s*(script|iframe|object|embed|svg|img|style|link|meta|form|input|body)\b/i',
            '/\bjavascript\s*:/i',
            '/\bon(error|load|click|mouseover|focus)\s*=/i',
            '/\{\{.*\}\}|\$\{.*\}|<\?php|<%/is',
        ],
        'system' => [
            '/(\.\.\/|\.\.\\\\|\/etc\/passwd|\/proc\/self|c:\\\\windows)/i',
            '/\b(rm\s+-rf|wget\s+http|curl\s+http|powershell\s+-|cmd\.exe|base64\s+-d|nc\s+-e)\b/i',
        ],
        'probe' => [
            '/\b(ignore|disregard|forget|override)\b.{0,40}\b(previous|prior|above|earlier|all|your|system)\b.{0,20}\b(instruction|instructions|prompt|rules|message)s?\b/i',
            '/\b(system prompt|developer message|jailbreak|dan mode|do anything now)\b/i',
            '/\b(reveal|show|print|dump|repeat|output|give|tell)\b.{0,30}\b(your|the|system)\s+(prompt|instructions|rules|configuration|config|settings)\b/i',
            '/\b(reveal|show|print|dump|list|give|tell|what)\b.{0,40}\b(secrets?|tokens?|credentials?|\.env|environment variables?|database|db|tables?|schema|columns?|server|source code)\b/i',
            '/\b(api[\s_-]?keys?|app_key|db_password|anthropic|passport[\s_-]?keys?|private keys?|client[\s_-]?secrets?)\b|(^|[^\w])\.env\b/i',
            '/\b(list|show|dump|export|give|get|see|display|find|search|look up|lookup|access|read)\b.{0,30}\b(all|every|other|another|someone|somebody|any)\b.{0,20}\b(applicants?|users?|people|persons?|records|applications?|cards?|passports?|staff|officers?)\b/i',
            "/\b(someone|somebody|another person|other people)'?s?\b.{0,30}\b(application|status|card|passport|details|record)s?\b/i",
        ],
    ];

    /**
     * Normalise a message: strip control characters and markup, collapse
     * whitespace and cap the length.
     */
    public function sanitize(string $message): string
    {
        $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $message) ?? '';
        $message = preg_replace('/\s+/u', ' ', $message) ?? '';

        return mb_substr(trim($message), 0, self::MAX_LENGTH);
    }

    /**
     * The threat category of a message, or null when it looks harmless.
     */
    public function threat(string $message): ?string
    {
        foreach (self::PATTERNS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message)) {
                    return $category;
                }
            }
        }

        return null;
    }

    public function refusal(string $category): string
    {
        return match ($category) {
            'probe' => 'I can only help with the residence card process and your own application. I cannot share system details or anyone else\'s information.',
            default => 'Sorry, I can\'t process that message. Please ask a plain question about the residence card, for example "What documents do I need?"',
        };
    }
}

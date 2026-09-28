<?php

namespace App\Assistant;

/**
 * A chat model used to phrase answers from the knowledge base.
 */
interface LanguageModel
{
    public function isAvailable(): bool;

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     * @return string|null the reply text, or null when the model refused or failed
     */
    public function reply(string $systemPrompt, array $messages): ?string;
}

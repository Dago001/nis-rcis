<?php

namespace Tests\Fakes;

use App\Assistant\LanguageModel;

/** A scripted stand-in for Claude that records what it was sent. */
class FakeLanguageModel implements LanguageModel
{
    public array $calls = [];

    public function __construct(public ?string $answer = 'You need your passport data page. See /faq for more.', public bool $available = true) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function reply(string $systemPrompt, array $messages): ?string
    {
        $this->calls[] = compact('systemPrompt', 'messages');

        return $this->answer;
    }
}

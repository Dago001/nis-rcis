<?php

namespace App\Assistant;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Claude (Anthropic API) via the official PHP SDK.
 *
 * Only the curated knowledge base and the visitor's own chat text are sent;
 * never database records, personal particulars or configuration.
 */
class ClaudeLanguageModel implements LanguageModel
{
    public function isAvailable(): bool
    {
        return filled(config('nis.assistant.api_key'));
    }

    public function reply(string $systemPrompt, array $messages): ?string
    {
        try {
            $client = new Client(
                apiKey: config('nis.assistant.api_key'),
                requestOptions: ['timeout' => 30.0, 'maxRetries' => 1],
            );

            $message = $client->beta->messages->create(
                maxTokens: 1024,
                messages: $messages,
                model: config('nis.assistant.model'),
                // The knowledge base is identical on every call: cache it.
                system: [['type' => 'text', 'text' => $systemPrompt, 'cacheControl' => ['type' => 'ephemeral']]],
                outputConfig: ['effort' => config('nis.assistant.effort')],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );

            if ($message->stopReason === 'refusal') {
                return null;
            }

            $text = '';
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $text .= $block->text;
                }
            }

            return trim($text) === '' ? null : $text;
        } catch (Throwable $e) {
            // Never surface API errors (they may carry request details) to the visitor.
            Log::warning('Assistant model call failed', ['error' => class_basename($e), 'code' => $e->getCode()]);

            return null;
        }
    }
}

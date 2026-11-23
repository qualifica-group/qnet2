<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use App\Services\AiAssistant\Prompt\AiChatPrompt;

/**
 * OpenRouter (https://openrouter.ai): OpenAI Chat Completions wire format with
 * an `<vendor>/<model>` id, optional attribution headers and provider routing.
 */
final class OpenRouterDriver extends OpenAiCompatibleDriver
{
    protected function driverName(): string
    {
        return AiChatDriverManager::DRIVER_OPENROUTER;
    }

    /**
     * OpenRouter documents `max_tokens` and maps it to each upstream provider.
     */
    protected function outputTokensParameter(): string
    {
        return 'max_tokens';
    }

    protected function headers(): array
    {
        return array_filter([
            ...parent::headers(),
            'HTTP-Referer' => (string) $this->setting('site_url'),
            'X-Title' => (string) $this->setting('app_name'),
        ], 'filled');
    }

    /**
     * `data_collection: deny` restricts routing to upstream providers that do
     * not store or train on prompts: messages may carry personal data.
     */
    protected function payload(AiChatPrompt $prompt): array
    {
        return parent::payload($prompt) + [
            'provider' => ['data_collection' => $this->setting('data_collection')],
        ];
    }
}

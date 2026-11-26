<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

/**
 * One streamed answer of the model: text, requested tool calls and what must be sent
 * back unchanged after them (reasoning blocks, in the order they arrived).
 */
final readonly class AgentModelResult
{
    /**
     * @param  list<array{id: string, name: string, arguments: string}>  $toolCalls
     * @param  list<array<string, mixed>>  $reasoningDetails
     * @param  array<string, mixed>  $rawUsage  the `usage` object of the last chunk, as OpenRouter sent it
     */
    public function __construct(
        public string $text,
        public array $toolCalls,
        public array $reasoningDetails,
        public ?string $reasoning,
        public string $finishReason,
        public array $rawUsage,
        public ?string $model,
        public ?string $provider,
    ) {}
}

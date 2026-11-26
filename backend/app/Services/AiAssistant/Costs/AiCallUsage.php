<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

/**
 * What one model call reported: tokens and the cost OpenRouter charged (`usage.cost`).
 * `costUsd` is null when the response carried no cost.
 */
final readonly class AiCallUsage
{
    public function __construct(
        public string $modelId,
        public ?string $provider,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedTokens,
        public int $reasoningTokens,
        public ?string $costUsd,
    ) {}

    /**
     * @param  array<string, mixed>  $usage  the `usage` object of a chat completion
     */
    public static function fromOpenRouter(string $modelId, ?string $provider, array $usage): self
    {
        $cost = $usage['cost'] ?? null;

        return new self(
            modelId: $modelId,
            provider: $provider,
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
            outputTokens: (int) ($usage['completion_tokens'] ?? 0),
            cachedTokens: (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0),
            reasoningTokens: (int) ($usage['completion_tokens_details']['reasoning_tokens'] ?? 0),
            costUsd: is_numeric($cost) ? AiMoney::from($cost) : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

/**
 * A mentioned record ready for the prompt: `context` is the compact JSON
 * projection, already truncated to `ai-assistant.mention_max_chars`.
 */
final readonly class ResolvedAiMention
{
    public function __construct(
        public string $module,
        public int $id,
        public string $label,
        public string $context,
    ) {}
}

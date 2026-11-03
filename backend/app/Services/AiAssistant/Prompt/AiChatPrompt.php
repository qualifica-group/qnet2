<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Prompt;

final readonly class AiChatPrompt
{
    /**
     * @param  list<AiPromptMessage>  $history
     */
    public function __construct(
        public string $systemInstructions,
        public ?string $pageGuide,
        public array $history,
        public AiPromptMessage $current,
        public string $locale,
    ) {}
}

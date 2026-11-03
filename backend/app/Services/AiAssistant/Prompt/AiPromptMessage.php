<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Prompt;

final readonly class AiPromptMessage
{
    /**
     * @param  'user'|'assistant'  $role
     * @param  list<AiPromptFile>  $files
     */
    public function __construct(
        public string $role,
        public string $text,
        public array $files = [],
    ) {}
}

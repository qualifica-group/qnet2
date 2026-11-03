<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Prompt;

final readonly class AiPromptFile
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $base64,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use App\Services\AiAssistant\Prompt\AiChatPrompt;
use Generator;

interface AiChatDriver
{
    /**
     * Streams the provider answer.
     *
     * @return Generator<int, AiStreamEvent>
     *
     * @throws AiProviderException
     */
    public function stream(AiChatPrompt $prompt): Generator;
}

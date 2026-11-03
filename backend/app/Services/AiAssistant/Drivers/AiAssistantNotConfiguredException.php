<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use RuntimeException;

final class AiAssistantNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The AI assistant is not configured.');
    }
}

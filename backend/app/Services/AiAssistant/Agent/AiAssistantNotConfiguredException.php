<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use RuntimeException;

/**
 * The model key or the connector entry is missing from the configuration: no row is written and no call is made.
 */
final class AiAssistantNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The AI assistant is not configured.');
    }
}

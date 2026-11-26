<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use RuntimeException;

/**
 * The general switch of the assistant settings is off: no row is written and no call is made.
 */
final class AiAssistantSwitchedOffException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The AI assistant is switched off.');
    }
}

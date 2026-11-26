<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use RuntimeException;

/**
 * The conversation already has a turn that is not final (spec 0433, D-16).
 */
final class AiTurnAlreadyActiveException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The assistant is still working on your previous message.');
    }
}

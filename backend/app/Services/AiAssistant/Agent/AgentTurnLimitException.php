<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiTurnErrorCode;
use RuntimeException;

/**
 * A cap of the turn (model calls, tool calls, duration) was reached.
 */
final class AgentTurnLimitException extends RuntimeException
{
    public function __construct(public readonly AiTurnErrorCode $errorCode)
    {
        parent::__construct('The turn reached a cap.');
    }
}

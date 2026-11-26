<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiTurnErrorCode;
use RuntimeException;

/**
 * The model call failed. The message is generic on purpose: the reason was already
 * logged without keys, URLs or content.
 */
final class AgentModelException extends RuntimeException
{
    public function __construct(public readonly AiTurnErrorCode $errorCode)
    {
        parent::__construct('The model call failed.');
    }
}

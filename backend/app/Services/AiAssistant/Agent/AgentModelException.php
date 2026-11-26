<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiTurnErrorCode;
use RuntimeException;

/**
 * The model call failed. The message is generic on purpose: the reason was already
 * logged without keys, URLs or content.
 *
 * `responseStarted` tells that part of the response had already arrived, so the provider may have
 * charged it: `rawUsage` is the `usage` object it declared, if any (spec 0433, D-7).
 */
final class AgentModelException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $rawUsage
     */
    public function __construct(
        public readonly AiTurnErrorCode $errorCode,
        public readonly bool $responseStarted = false,
        public readonly array $rawUsage = [],
    ) {
        parent::__construct('The model call failed.');
    }
}

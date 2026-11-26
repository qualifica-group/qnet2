<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use RuntimeException;

/**
 * The person already has the maximum of turns not yet final across all their conversations.
 */
final class AiTooManyActiveTurnsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You have too many assistant requests running at once. Wait for one to finish.');
    }
}

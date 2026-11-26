<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Preview;

use RuntimeException;

/**
 * A confirmation or cancellation that cannot go on (spec 0433, `confirm`/`cancel` errors): the
 * preview is not the one of the turn (404) or it is not open any more (409, with the reason).
 */
final class AiPreviewDecisionException extends RuntimeException
{
    public const string NOT_FOUND = 'not_found';

    public const string EXPIRED = 'expired';

    public const string ALREADY_USED = 'already_used';

    public const string NOT_OPEN = 'not_open';

    public const string NOT_CONFIRMABLE = 'not_confirmable';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}

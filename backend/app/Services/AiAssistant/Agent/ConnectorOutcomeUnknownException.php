<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use RuntimeException;

/**
 * A confirmation left QNET2 but its answer was lost or unreadable (timeout, 5xx, body that is not the
 * envelope): the write may have happened, so it is never repeated (spec: «Esito incerto di una conferma»).
 */
final class ConnectorOutcomeUnknownException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The outcome of the confirmation is not known.');
    }
}

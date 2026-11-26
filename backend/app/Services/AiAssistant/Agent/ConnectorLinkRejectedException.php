<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use RuntimeException;

/**
 * The connector refused the link credential (401): the link fell, the turn cannot go on.
 */
final class ConnectorLinkRejectedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The assistant link was refused.');
    }
}

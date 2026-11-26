<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use RuntimeException;

/**
 * The connector could not be reached, answered with an error status or with a body that is not MCP.
 */
final class ConnectorUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The assistant could not reach its tools.');
    }
}

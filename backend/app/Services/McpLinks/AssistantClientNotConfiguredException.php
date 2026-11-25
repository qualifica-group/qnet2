<?php

namespace App\Services\McpLinks;

use RuntimeException;

/**
 * The API client of the connector, where assistant links belong, is not set or not active.
 */
class AssistantClientNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The API client of the connector for the assistant is not configured or not active.');
    }
}

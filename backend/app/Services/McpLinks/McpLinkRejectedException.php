<?php

namespace App\Services\McpLinks;

use App\Enums\McpLinkReason;
use RuntimeException;

/**
 * An exchange refused for the given reason.
 */
class McpLinkRejectedException extends RuntimeException
{
    public function __construct(public readonly McpLinkReason $reason)
    {
        parent::__construct('MCP link rejected: '.$reason->value);
    }
}

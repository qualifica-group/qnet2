<?php

namespace App\Services\McpLinks;

use RuntimeException;

/**
 * An exchange refused: the reason is one of link_unknown, link_revoked,
 * link_expired, user_inactive, password_changed.
 */
class McpLinkRejectedException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('MCP link rejected: '.$reason);
    }
}

<?php

namespace App\Services\McpLinks;

use RuntimeException;
use Throwable;

/**
 * An explicit revocation marked the link fallen but could not delete its tokens.
 * The tokens are refused anyway; the client repeats the revocation to finish it.
 */
class McpLinkTokensNotDeletedException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('The tokens of the revoked MCP link could not be deleted.', 0, $previous);
    }
}

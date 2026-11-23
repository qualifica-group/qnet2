<?php

namespace App\Services\McpLinks;

use RuntimeException;

/**
 * mcp-link.release_commit (MCP_LINK_RELEASE_COMMIT) is not set: a server
 * configuration error, not a refusal of the link.
 */
class ReleaseCommitNotConfiguredException extends RuntimeException
{
    public const string REASON = 'release_commit_not_configured';

    public function __construct()
    {
        parent::__construct('The release commit is not configured.');
    }
}

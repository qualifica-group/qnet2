<?php

namespace App\DataObjects\McpLinks;

use App\Models\McpLink;
use App\Models\User;

/**
 * Outcome of opening an MCP link: the link, the credential (returned once, never
 * stored) and the user it belongs to.
 */
final readonly class OpenedMcpLink
{
    public function __construct(
        public McpLink $link,
        public string $credential,
        public User $user,
    ) {}
}

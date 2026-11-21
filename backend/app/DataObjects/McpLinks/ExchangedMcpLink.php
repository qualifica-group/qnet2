<?php

namespace App\DataObjects\McpLinks;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Outcome of an MCP link exchange: the plain text user token, its expiry and the commit of QNET2 in service.
 */
final readonly class ExchangedMcpLink
{
    public function __construct(
        public User $user,
        public string $token,
        public CarbonInterface $expiresAt,
        public string $releaseCommit,
    ) {}
}

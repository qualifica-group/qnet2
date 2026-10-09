<?php

namespace App\DataObjects\ApiClients;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Outcome of POST /api/auth/client-login (spec 0210): the user and the plain
 * text token bound to the API client, with its expiry.
 */
final readonly class ClientLoginResult
{
    public function __construct(
        public User $user,
        public string $token,
        public CarbonInterface $expiresAt,
    ) {}
}

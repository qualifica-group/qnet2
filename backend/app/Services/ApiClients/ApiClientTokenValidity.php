<?php

namespace App\Services\ApiClients;

use App\Models\ApiClient;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Validity of the tokens bound to an API client (spec 0210, R-1..R-3), plugged
 * into Sanctum::authenticateAccessTokensUsing.
 *
 * - key (token of the technical user): valid while the client is active and
 *   not expired; the global SANCTUM_EXPIRATION does not apply;
 * - user token issued by client-login: valid until its own expires_at, while
 *   the user and the client are active (and the client not expired);
 * - any other token: Sanctum's verdict, untouched.
 */
class ApiClientTokenValidity
{
    public function isValid(PersonalAccessToken $token, bool $sanctumVerdict): bool
    {
        if ($token->api_client_id === null) {
            return $sanctumVerdict;
        }

        $client = ApiClient::query()->find($token->api_client_id);
        $user = $token->tokenable;

        if ($client === null || ! $user instanceof User || ! $client->is_active || $client->isExpired()) {
            return false;
        }

        if ($user->isServiceAccount()) {
            return true;
        }

        return $user->is_active && ($token->expires_at === null || ! $token->expires_at->isPast());
    }
}

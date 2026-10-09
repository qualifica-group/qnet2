<?php

namespace App\Support\ApiClients;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The API client a request is made through (spec 0210), resolved once per
 * request from the client binding of the current Sanctum token and shared via
 * the request attributes. Null for every request not made with a client token.
 */
final class ApiClientRequestContext
{
    private const string ATTRIBUTE = 'api_client';

    public static function resolve(Request $request): ?ApiClient
    {
        if ($request->attributes->has(self::ATTRIBUTE)) {
            return $request->attributes->get(self::ATTRIBUTE);
        }

        $user = $request->user('sanctum');
        $clientId = $user instanceof User ? $user->currentApiClientId() : null;
        $client = $clientId === null ? null : ApiClient::query()->find($clientId);

        $request->attributes->set(self::ATTRIBUTE, $client);

        return $client;
    }

    /**
     * The client of an already-resolved request, without touching auth.
     */
    public static function current(Request $request): ?ApiClient
    {
        return $request->attributes->get(self::ATTRIBUTE);
    }
}

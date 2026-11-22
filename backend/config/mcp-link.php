<?php

/*
|--------------------------------------------------------------------------
| MCP link module
|--------------------------------------------------------------------------
|
| An MCP connector, authenticated with its API client key, opens a long-lived
| link credential for a user and exchanges it for short-lived user tokens.
| Every duration and limit is configuration with a default value.
|
*/

return [
    // Random bytes of a link credential (returned hex-encoded, twice as long).
    'credential_bytes' => 32,

    // Longest accepted name of the MCP client.
    'mcp_client_max_length' => 120,

    // Lifetime of a link credential, in days.
    'link_ttl_days' => (int) env('MCP_LINK_TTL_DAYS', 365),

    // Lifetime, in minutes, of a user token issued by an exchange (never beyond the link).
    'access_token_ttl_minutes' => (int) env('MCP_LINK_ACCESS_TOKEN_TTL_MINUTES', 60),

    // Failed openings before the route answers 429, with the same answer in every case:
    // `max` per address as the database compares addresses (the same counter whether the
    // account exists or not) and
    // `client_max` per API client, so that spraying addresses is bounded too.
    'open_attempts' => [
        'max' => (int) env('MCP_LINK_OPEN_MAX_ATTEMPTS', 5),
        'client_max' => (int) env('MCP_LINK_OPEN_CLIENT_MAX_ATTEMPTS', 50),
        'decay_minutes' => (int) env('MCP_LINK_OPEN_DECAY_MINUTES', 15),
    ],

    // Commit of QNET2 in service. Required: QNET2 exposes no version elsewhere, so
    // the release sets it every time. Unset or empty, the exchange issues no token
    // and answers 500 with reason `release_commit_not_configured`.
    'release_commit' => env('MCP_LINK_RELEASE_COMMIT'),
];

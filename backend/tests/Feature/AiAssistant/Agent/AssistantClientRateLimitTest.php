<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The assistant links of the whole company go through one API client: a quota per client alone
// would be shared by everybody, so for that client the counter is per person too.
it('gives each person a quota of their own on the API client of the assistant', function () {
    [$client] = createApiClientWithKey(['rate_limit_per_minute' => 2]);
    config()->set('mcp-link.assistant_client_id', $client->id);
    $first = createClientLoginToken(User::factory()->create(), $client);
    $second = createClientLoginToken(User::factory()->create(), $client);

    callWithToken('GET', '/api/auth/me', $first)->assertOk();
    callWithToken('GET', '/api/auth/me', $first)->assertOk();
    callWithToken('GET', '/api/auth/me', $first)->assertStatus(429);

    callWithToken('GET', '/api/auth/me', $second)->assertOk();
    callWithToken('GET', '/api/auth/me', $second)->assertOk();
    callWithToken('GET', '/api/auth/me', $second)->assertStatus(429);
});

it('keeps one shared quota on every other API client', function () {
    [$client] = createApiClientWithKey(['rate_limit_per_minute' => 2]);
    [$assistantClient] = createApiClientWithKey();
    config()->set('mcp-link.assistant_client_id', $assistantClient->id);
    $first = createClientLoginToken(User::factory()->create(), $client);
    $second = createClientLoginToken(User::factory()->create(), $client);

    callWithToken('GET', '/api/auth/me', $first)->assertOk();
    callWithToken('GET', '/api/auth/me', $second)->assertOk();
    callWithToken('GET', '/api/auth/me', $first)->assertStatus(429);
});

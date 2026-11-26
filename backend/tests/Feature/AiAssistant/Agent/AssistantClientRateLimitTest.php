<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The API client of the assistant is the one of the connector, shared with ChatGPT and Claude: its
// per-minute quota stays per client (a rate limit is a security control, not loosened without a decision).
it('keeps one shared quota on the API client of the assistant', function () {
    [$client] = createApiClientWithKey(['rate_limit_per_minute' => 2]);
    config()->set('mcp-link.assistant_client_id', $client->id);
    $first = createClientLoginToken(User::factory()->create(), $client);
    $second = createClientLoginToken(User::factory()->create(), $client);

    callWithToken('GET', '/api/auth/me', $first)->assertOk();
    callWithToken('GET', '/api/auth/me', $second)->assertOk();
    callWithToken('GET', '/api/auth/me', $first)->assertStatus(429);
    callWithToken('GET', '/api/auth/me', $second)->assertStatus(429);
});

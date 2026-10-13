<?php

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const API_CLIENT_PAYLOAD = ['name' => 'Landing page'];

// AC-002
it('creates a client with its technical user and a single database-backed key, never exposing the hash', function () {
    $admin = apiClientAdmin(['create', 'view']);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/api-clients', API_CLIENT_PAYLOAD + ['description' => 'Marketing'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.client.name', 'Landing page')
        ->assertJsonPath('data.client.created_by.id', $admin->id)
        ->assertJsonPath('data.client.service_user.name', 'API · Landing page')
        ->assertJsonPath('data.client.effective_rate_limit_per_minute', 60)
        ->assertJsonPath('data.client.is_expired', false)
        ->assertJsonPath('data.client.last_used_at', null);

    $key = $response->json('data.plain_text_key');
    $client = ApiClient::query()->firstOrFail();
    $serviceUser = $client->serviceUser;

    expect($key)->toBeString()->not->toBeEmpty()
        ->and($client->key_last_four)->toBe(substr($key, -4))
        ->and($client->tokens)->toHaveCount(1)
        ->and($client->tokens->first()->tokenable_id)->toBe($serviceUser->id)
        ->and($serviceUser->isServiceAccount())->toBeTrue()
        ->and($serviceUser->is_active)->toBeTrue()
        ->and($serviceUser->hasRole('super-admin'))->toBeTrue()
        ->and($serviceUser->email)->toEndWith('@'.config('external-api.service_users.email_domain'))
        ->and($response->getContent())->not->toContain($client->tokens->first()->token);

    $show = $this->getJson("/api/api-clients/{$client->id}")->assertOk();

    expect($show->getContent())->not->toContain($key)
        ->and($show->json('data'))->not->toHaveKeys(['plain_text_key', 'token', 'scopes'])
        ->and(array_keys($show->json('data')))->toBe([
            'id', 'name', 'description', 'rate_limit_per_minute', 'effective_rate_limit_per_minute',
            'expires_at', 'is_active', 'is_expired', 'key_last_four', 'last_used_at', 'service_user',
            'created_by', 'created_at', 'updated_at',
        ]);
});

it('exposes the max last_used_at of the tokens bound to the client', function () {
    Sanctum::actingAs(apiClientAdmin(['view']));
    [$client] = createApiClientWithKey();
    $client->tokens()->update(['last_used_at' => '2026-10-01 10:00:00']);

    $this->getJson("/api/api-clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.last_used_at', '2026-10-01T10:00:00+00:00');
});

it('returns 404 for an unknown client', function () {
    Sanctum::actingAs(apiClientAdmin(['view', 'update', 'delete']));

    $this->getJson('/api/api-clients/999999')->assertNotFound();
    $this->postJson('/api/api-clients/999999/rotate-key')->assertNotFound();
    $this->deleteJson('/api/api-clients/999999')->assertNotFound();
});

it('requires authentication and has no scopes endpoint', function () {
    $this->postJson('/api/api-clients', API_CLIENT_PAYLOAD)->assertUnauthorized();

    Sanctum::actingAs(apiClientAdmin(['view']));
    $this->getJson('/api/api-clients/scopes')->assertNotFound();
});

it('answers 403 on every admin endpoint without the matching permission, with no write', function () {
    [$client] = createApiClientWithKey();
    $tokensBefore = PersonalAccessToken::query()->count();

    // Holds only `view`: reads allowed, every write denied.
    Sanctum::actingAs(apiClientAdmin(['view']));
    $this->postJson('/api/api-clients', API_CLIENT_PAYLOAD)->assertForbidden();
    $this->patchJson("/api/api-clients/{$client->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->postJson("/api/api-clients/{$client->id}/rotate-key")->assertForbidden();
    $this->deleteJson("/api/api-clients/{$client->id}")->assertForbidden();

    // Holds everything but `view`: reads denied.
    Sanctum::actingAs(apiClientAdmin(['create', 'update', 'delete']));
    $this->getJson("/api/api-clients/{$client->id}")->assertForbidden();

    expect(ApiClient::query()->count())->toBe(1)
        ->and($client->fresh()->name)->not->toBe('Renamed')
        ->and(PersonalAccessToken::query()->count())->toBe($tokensBefore);
});

it('rejects invalid payloads with 422 and creates nothing', function (array $override, string $field) {
    Sanctum::actingAs(apiClientAdmin(['create']));
    ApiClient::factory()->create(['name' => 'Taken']);

    $this->postJson('/api/api-clients', [...API_CLIENT_PAYLOAD, ...$override])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(ApiClient::query()->count())->toBe(1);
})->with([
    'duplicate name' => [['name' => 'Taken'], 'name'],
    'expiry in the past' => [['expires_at' => '2020-01-01 00:00:00'], 'expires_at'],
    'rate limit above the cap' => [['rate_limit_per_minute' => 1001], 'rate_limit_per_minute'],
    'rate limit below 1' => [['rate_limit_per_minute' => 0], 'rate_limit_per_minute'],
]);

it('allows the unchanged name on update but rejects another client name', function () {
    Sanctum::actingAs(apiClientAdmin(['update']));
    [$client] = createApiClientWithKey(['name' => 'Mine']);
    ApiClient::factory()->create(['name' => 'Other']);

    $this->patchJson("/api/api-clients/{$client->id}", ['name' => 'Mine'])->assertOk();
    $this->patchJson("/api/api-clients/{$client->id}", ['name' => 'Other'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('renames the technical user together with the client', function () {
    Sanctum::actingAs(apiClientAdmin(['update']));
    [$client] = createApiClientWithKey(['name' => 'Old name']);

    $this->patchJson("/api/api-clients/{$client->id}", ['name' => 'New name'])
        ->assertOk()
        ->assertJsonPath('data.service_user.name', 'API · New name');

    expect($client->serviceUser->fresh()->name)->toBe('API · New name');
});

// AC-007
it('rotates the key: the old one is revoked, the new one works, the client-login tokens survive', function () {
    Sanctum::actingAs(apiClientAdmin(['update']));
    [$client, $oldKey] = createApiClientWithKey();
    $loginToken = createClientLoginToken(User::factory()->create(), $client);

    $newKey = $this->postJson("/api/api-clients/{$client->id}/rotate-key")
        ->assertOk()
        ->assertJsonPath('data.client.key_last_four', fn ($v) => is_string($v) && strlen($v) === 4)
        ->json('data.plain_text_key');

    expect($client->tokens()->where('tokenable_id', $client->service_user_id)->count())->toBe(1)
        ->and($client->tokens()->count())->toBe(2)
        ->and($client->fresh()->key_last_four)->toBe(substr($newKey, -4));

    callWithToken('GET', '/api/auth/me', $oldKey)->assertUnauthorized();
    callWithToken('GET', '/api/auth/me', $newKey)->assertOk();
    callWithToken('GET', '/api/auth/me', $loginToken)->assertOk();
});

it('deactivates a client through update', function () {
    Sanctum::actingAs(apiClientAdmin(['update']));
    [$client] = createApiClientWithKey();

    $this->patchJson("/api/api-clients/{$client->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('deletes the client and its tokens, keeping the technical user deactivated', function () {
    Sanctum::actingAs(apiClientAdmin(['delete']));
    [$client] = createApiClientWithKey();
    $serviceUser = $client->serviceUser;
    createClientLoginToken(User::factory()->create(), $client);

    $this->deleteJson("/api/api-clients/{$client->id}")->assertOk()->assertJsonPath('success', true);

    expect(ApiClient::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and($serviceUser->fresh()->is_active)->toBeFalse()
        ->and($serviceUser->fresh()->isServiceAccount())->toBeTrue();
});

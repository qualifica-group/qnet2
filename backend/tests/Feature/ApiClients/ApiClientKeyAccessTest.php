<?php

use App\Models\Campaign;
use App\Models\CustomFieldDefinition;
use App\Models\Lead;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\ApiClients\ApiClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @return array<string, int>
 */
function leadPayloadForApiClient(): array
{
    return [
        'registry_id' => Registry::factory()->create()->id,
        'campaign_id' => Campaign::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
    ];
}

// AC-003
it('acts as the technical super-admin on the regular API and audits it with the client', function () {
    [$client, $key] = createApiClientWithKey();
    $lead = Lead::factory()->create();

    callWithToken('GET', "/api/leads/{$lead->id}", $key)->assertOk();

    $id = callWithToken('POST', '/api/leads', $key, leadPayloadForApiClient())
        ->assertCreated()
        ->json('data.id');

    $activity = Activity::query()->where('subject_type', 'lead')->where('subject_id', $id)->latest('id')->firstOrFail();

    expect($activity->causer_id)->toBe($client->service_user_id)
        ->and($activity->properties->get('api_client_id'))->toBe($client->id);
});

it('records no api_client_id for requests made without a client token', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('leads.create'));

    callWithToken('POST', '/api/leads', $user->createToken('app')->plainTextToken, leadPayloadForApiClient())->assertCreated();

    expect(Activity::query()->where('subject_type', 'lead')->latest('id')->firstOrFail()->properties->has('api_client_id'))->toBeFalse();
});

// AC-010
it('accepts a valid custom field on lead creation (the actor is a User)', function () {
    CustomFieldDefinition::factory()->forEntity('leads')->ofType('text')->create(['key' => 'notes']);
    [, $key] = createApiClientWithKey();

    $id = callWithToken('POST', '/api/leads', $key, [...leadPayloadForApiClient(), 'custom_fields' => ['notes' => 'from the client']])
        ->assertCreated()
        ->json('data.id');

    expect(Lead::query()->findOrFail($id)->custom_fields)->toBe(['notes' => 'from the client']);
});

// AC-004
it('rejects the key when the client is inactive, expired or the key was rotated', function () {
    [$client, $key] = createApiClientWithKey();
    callWithToken('GET', '/api/auth/me', $key)->assertOk();

    $client->update(['is_active' => false]);
    callWithToken('GET', '/api/auth/me', $key)->assertUnauthorized();

    $client->update(['is_active' => true, 'expires_at' => now()->subMinute()]);
    callWithToken('GET', '/api/auth/me', $key)->assertUnauthorized();

    $client->update(['expires_at' => null]);
    callWithToken('GET', '/api/auth/me', $key)->assertOk();

    app(ApiClientService::class)->rotateKey($client, User::factory()->create());
    callWithToken('GET', '/api/auth/me', $key)->assertUnauthorized();
});

it('keeps a key older than the global expiration valid, but not an app token (R-1, R-3)', function () {
    [$client, $key] = createApiClientWithKey();
    $client->tokens()->update(['created_at' => now()->subDays(40)]);

    callWithToken('GET', '/api/auth/me', $key)->assertOk();

    $user = User::factory()->create();
    $appToken = $user->createToken('app');
    $appToken->accessToken->forceFill(['created_at' => now()->subDays(40)])->save();

    callWithToken('GET', '/api/auth/me', $appToken->plainTextToken)->assertUnauthorized();
});

// AC-008
it('throttles per client with an envelope and Retry-After, sharing the counter with client-login tokens', function () {
    [$client, $key] = createApiClientWithKey(['rate_limit_per_minute' => 2]);
    $loginToken = createClientLoginToken(User::factory()->create(), $client);

    callWithToken('GET', '/api/auth/me', $key)->assertOk()->assertHeader('X-RateLimit-Limit', 2);
    callWithToken('GET', '/api/auth/me', $loginToken)->assertOk();

    callWithToken('GET', '/api/auth/me', $key)
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('success', false);
});

it('does not throttle the tokens of the app nor another client', function () {
    [, $key] = createApiClientWithKey(['rate_limit_per_minute' => 1]);
    [, $otherKey] = createApiClientWithKey();
    $appToken = User::factory()->create()->createToken('app')->plainTextToken;

    callWithToken('GET', '/api/auth/me', $key)->assertOk();
    callWithToken('GET', '/api/auth/me', $key)->assertStatus(429);
    callWithToken('GET', '/api/auth/me', $otherKey)->assertOk();

    foreach (range(1, 5) as $ignored) {
        callWithToken('GET', '/api/auth/me', $appToken)->assertOk();
    }
});

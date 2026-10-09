<?php

use App\Models\Campaign;
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
 * @return array<string, mixed>
 */
function clientLoginBody(User $user, string $password = 'password'): array
{
    return ['email' => $user->email, 'password' => $password];
}

// AC-005
it('logs a user in through the client key and returns a token that acts as that user', function () {
    [$client, $key] = createApiClientWithKey();
    $user = User::factory()->create();

    $response = callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody($user) + ['device_name' => 'crm'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

    $token = $response->json('data.token');
    $stored = $user->tokens()->firstOrFail();

    expect($stored->api_client_id)->toBe($client->id)
        ->and($stored->name)->toBe('crm')
        ->and($stored->expires_at->isBetween(now()->addMinutes(1439), now()->addMinutes(1441)))->toBeTrue()
        ->and($response->json('data.expires_at'))->toBe($stored->expires_at->toIso8601String());

    callWithToken('GET', '/api/auth/me', $token)->assertOk()->assertJsonPath('data.id', $user->id);
});

it('answers every credential failure with the same 422 message', function (Closure $makeUser, string $password) {
    [, $key] = createApiClientWithKey();
    $user = $makeUser();

    $messages = callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody($user, $password))
        ->assertUnprocessable()
        ->json('errors.email');

    expect($messages)->toBe([__('auth.failed')])
        ->and($user->tokens()->count())->toBe(0);
})->with([
    'wrong password' => [fn () => User::factory()->create(), 'wrong'],
    'inactive user' => [fn () => User::factory()->inactive()->create(), 'password'],
    'technical user' => [fn () => User::factory()->serviceAccount()->create(), 'password'],
]);

it('requires the client key as Bearer', function () {
    [$client] = createApiClientWithKey();
    $user = User::factory()->create();

    $this->postJson('/api/auth/client-login', clientLoginBody($user))->assertUnauthorized();

    $appToken = User::factory()->create()->createToken('app')->plainTextToken;
    callWithToken('POST', '/api/auth/client-login', $appToken, clientLoginBody($user))
        ->assertForbidden()
        ->assertJsonPath('success', false);

    $loginToken = createClientLoginToken(User::factory()->create(), $client);
    callWithToken('POST', '/api/auth/client-login', $loginToken, clientLoginBody($user))->assertForbidden();

    expect($user->tokens()->count())->toBe(0);
});

it('throttles client-login at six attempts per minute', function () {
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();

    foreach (range(1, 6) as $ignored) {
        callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody($user, 'wrong'))->assertUnprocessable();
    }

    callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody($user))->assertStatus(429);
});

// AC-006
it('applies the permissions of the logged-in user and audits the user with the client', function () {
    [$client, $key] = createApiClientWithKey();
    Permission::findOrCreate('leads.view');
    Permission::findOrCreate('leads.create');
    $viewer = User::factory()->create()->givePermissionTo('leads.view');
    $creator = User::factory()->create()->givePermissionTo('leads.create');
    $lead = Lead::factory()->create();
    $payload = [
        'registry_id' => Registry::factory()->create()->id,
        'campaign_id' => Campaign::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
    ];

    $viewerToken = callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody($viewer))->json('data.token');
    callWithToken('GET', "/api/leads/{$lead->id}", $viewerToken)->assertOk();
    callWithToken('POST', '/api/leads', $viewerToken, $payload)->assertForbidden();

    $creatorToken = callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody($creator))->json('data.token');
    $id = callWithToken('POST', '/api/leads', $creatorToken, $payload)->assertCreated()->json('data.id');

    $activity = Activity::query()->where('subject_type', 'lead')->where('subject_id', $id)->latest('id')->firstOrFail();

    expect($activity->causer_id)->toBe($creator->id)
        ->and($activity->properties->get('api_client_id'))->toBe($client->id);
});

// AC-007
it('invalidates the client-login token on logout, expiry, user or client deactivation and client deletion', function () {
    [$client, $key] = createApiClientWithKey();
    $login = fn (User $user): string => callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody($user))->json('data.token');

    $loggedOut = $login(User::factory()->create());
    callWithToken('GET', '/api/auth/me', $loggedOut)->assertOk();
    callWithToken('POST', '/api/auth/logout', $loggedOut)->assertOk();
    callWithToken('GET', '/api/auth/me', $loggedOut)->assertUnauthorized();

    $expired = createClientLoginToken(User::factory()->create(), $client, now()->subMinute());
    callWithToken('GET', '/api/auth/me', $expired)->assertUnauthorized();

    $user = User::factory()->create();
    $deactivated = $login($user);
    $user->update(['is_active' => false]);
    callWithToken('GET', '/api/auth/me', $deactivated)->assertUnauthorized();

    $viaClient = $login(User::factory()->create());
    $client->update(['is_active' => false]);
    callWithToken('GET', '/api/auth/me', $viaClient)->assertUnauthorized();

    $client->update(['is_active' => true]);
    callWithToken('GET', '/api/auth/me', $viaClient)->assertOk();

    $client->delete();
    callWithToken('GET', '/api/auth/me', $viaClient)->assertUnauthorized();
});

it('keeps the client-login token valid across a key rotation', function () {
    [$client, $key] = createApiClientWithKey();
    $token = callWithToken('POST', '/api/auth/client-login', $key, clientLoginBody(User::factory()->create()))->json('data.token');

    app(ApiClientService::class)->rotateKey($client, User::factory()->create());

    callWithToken('GET', '/api/auth/me', $token)->assertOk();
});

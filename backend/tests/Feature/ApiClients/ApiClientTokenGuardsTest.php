<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('refreshes a client-login token keeping its client and expiry', function () {
    [$client] = createApiClientWithKey();
    $user = User::factory()->create();
    $expiresAt = now()->addHours(5)->startOfSecond();
    $old = createClientLoginToken($user, $client, $expiresAt);

    $new = callWithToken('POST', '/api/auth/refresh', $old)->assertOk()->json('data.token');

    callWithToken('GET', '/api/auth/me', $old)->assertUnauthorized();
    $stored = $user->tokens()->firstOrFail();

    expect($user->tokens()->count())->toBe(1)
        ->and($stored->api_client_id)->toBe($client->id)
        ->and($stored->expires_at->equalTo($expiresAt))->toBeTrue();
    callWithToken('GET', '/api/auth/me', $new)->assertOk();
});

it('still refreshes an app token without binding it to a client', function () {
    $user = User::factory()->create();
    $new = callWithToken('POST', '/api/auth/refresh', $user->createToken('app')->plainTextToken)->assertOk()->json('data.token');

    $stored = $user->tokens()->firstOrFail();
    expect($stored->api_client_id)->toBeNull()->and($stored->expires_at)->toBeNull();
    callWithToken('GET', '/api/auth/me', $new)->assertOk();
});

it('refuses to refresh a client key', function () {
    [$client, $key] = createApiClientWithKey();

    callWithToken('POST', '/api/auth/refresh', $key)
        ->assertForbidden()
        ->assertJsonPath('success', false);

    callWithToken('GET', '/api/auth/me', $key)->assertOk();
    expect($client->tokens()->count())->toBe(1);
});

it('refuses impersonation start and stop to tokens bound to a client', function () {
    [$client, $key] = createApiClientWithKey();
    $target = User::factory()->create();
    Permission::findOrCreate('users.impersonate');
    $superAdmin = User::factory()->create()->assignRole(Role::findByName('super-admin', 'web'));
    $loginToken = createClientLoginToken($superAdmin, $client);

    callWithToken('POST', "/api/users/{$target->id}/impersonate", $key)->assertForbidden()->assertJsonPath('success', false);
    callWithToken('POST', "/api/users/{$target->id}/impersonate", $loginToken)->assertForbidden()->assertJsonPath('success', false);
    callWithToken('POST', '/api/auth/stop-impersonation', $loginToken)->assertForbidden()->assertJsonPath('success', false);

    expect($target->tokens()->count())->toBe(0);
});

it('runs no api_clients query for a request authenticated with Sanctum::actingAs', function () {
    Sanctum::actingAs(User::factory()->create());
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->getJson('/api/auth/me')->assertOk();

    expect(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'api_clients')))->toBeEmpty();
});

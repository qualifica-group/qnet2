<?php

declare(strict_types=1);

use App\Models\ApiClient;
use App\Models\User;
use App\Services\ApiClients\ApiClientService;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

if (! function_exists('apiClientAdmin')) {
    /**
     * A non super-admin granted exactly the given `api-clients.*` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function apiClientAdmin(array $abilities): User
    {
        foreach (['view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("api-clients.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("api-clients.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('createApiClientWithKey')) {
    /**
     * A client created through the real service: technical super-admin user and
     * one database-backed key (never Sanctum::actingAs()'s mock).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: ApiClient, 1: string} the client and its plain-text key
     */
    function createApiClientWithKey(array $attributes = []): array
    {
        $result = app(ApiClientService::class)->create(
            ['name' => 'Client '.fake()->unique()->word(), ...$attributes],
            User::factory()->create(),
        );

        return [$result['client'], $result['plain_text_key']];
    }
}

if (! function_exists('createClientLoginToken')) {
    /**
     * A user token bound to the client, as POST /api/auth/client-login issues it.
     */
    function createClientLoginToken(User $user, ApiClient $client, ?DateTimeInterface $expiresAt = null): string
    {
        $newToken = $user->createToken('api-client', ['*'], $expiresAt ?? now()->addDay());
        $newToken->accessToken->forceFill(['api_client_id' => $client->id])->save();

        return $newToken->plainTextToken;
    }
}

if (! function_exists('callWithToken')) {
    /**
     * A JSON call authenticated with a real Bearer token. The auth guards are
     * dropped first: the app instance is reused inside one test, so a guard
     * would otherwise keep the actor resolved by the previous request.
     *
     * @param  array<string, mixed>  $data
     */
    function callWithToken(string $method, string $uri, string $token, array $data = []): TestResponse
    {
        app('auth')->forgetGuards();

        return test()->json($method, $uri, $data, ['Authorization' => 'Bearer '.$token]);
    }
}

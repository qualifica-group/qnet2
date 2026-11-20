<?php

use App\Enums\ApiClientMethod;
use App\Models\Lead;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0392 AC-001
it('lets a GET-only client read, list through a read-only POST and nothing else', function () {
    [, $key] = createApiClientWithKey(['allowed_methods' => ['GET']]);
    $source = Source::factory()->create();

    callWithToken('GET', "/api/sources/{$source->id}", $key)->assertOk();
    callWithToken('POST', '/api/tables/sources/rows', $key, ['startRow' => 0, 'endRow' => 10])->assertOk();

    callWithToken('POST', '/api/sources', $key, ['name' => 'Nuova'])
        ->assertForbidden()
        ->assertExactJson(['success' => false, 'message' => 'This API client is not allowed to use the POST method.']);
    callWithToken('PUT', "/api/sources/{$source->id}", $key, ['name' => 'X'])->assertForbidden();
    callWithToken('PATCH', "/api/sources/{$source->id}", $key, ['name' => 'X'])->assertForbidden();
    callWithToken('DELETE', "/api/sources/{$source->id}", $key)->assertForbidden();

    $this->assertDatabaseHas('sources', ['id' => $source->id, 'name' => $source->name]);
});

it('treats PATCH as PUT and lets a client without GET do nothing but its allowed writes', function () {
    [, $key] = createApiClientWithKey(['allowed_methods' => ['PUT']]);
    $source = Source::factory()->create();

    callWithToken('PATCH', "/api/sources/{$source->id}", $key, ['name' => 'Rinominata'])->assertOk();
    callWithToken('GET', "/api/sources/{$source->id}", $key)->assertForbidden();
    callWithToken('POST', '/api/tables/sources/rows', $key, ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

// AC-002
it('applies the client methods to the user tokens of client-login, which with logout stays always allowed', function () {
    [$client, $key] = createApiClientWithKey(['allowed_methods' => ['GET']]);
    $user = User::factory()->create();

    $token = callWithToken('POST', '/api/auth/client-login', $key, ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->json('data.token');

    callWithToken('DELETE', '/api/leads/'.Lead::factory()->create()->id, $token)
        ->assertForbidden()
        ->assertJsonPath('message', 'This API client is not allowed to use the DELETE method.');
    callWithToken('POST', '/api/auth/logout', $token)->assertOk();

    expect($user->tokens()->where('api_client_id', $client->id)->count())->toBe(0);
});

// AC-003
it('leaves the requests of the app untouched', function () {
    createApiClientWithKey(['allowed_methods' => ['GET']]);
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('sources.create'));

    callWithToken('POST', '/api/sources', $user->createToken('app')->plainTextToken, ['name' => 'Dall app'])->assertCreated();
});

// AC-004
it('defaults to every method, validates the list and exposes it', function () {
    Sanctum::actingAs(apiClientAdmin(['create', 'update', 'view']));

    $id = $this->postJson('/api/api-clients', ['name' => 'ERP'])
        ->assertCreated()
        ->assertJsonPath('data.client.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE'])
        ->json('data.client.id');

    foreach (['allowed_methods' => [], 'allowed_methods.1' => ['GET', 'GET'], 'allowed_methods.0' => ['PATCH']] as $field => $invalid) {
        $this->patchJson("/api/api-clients/{$id}", ['allowed_methods' => $invalid])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    $this->patchJson("/api/api-clients/{$id}", ['allowed_methods' => ['DELETE', 'GET']])
        ->assertOk()
        ->assertJsonPath('data.allowed_methods', ['GET', 'DELETE']);
});

it('maps every HTTP method to the permission that governs it', function (string $httpMethod, bool $readOnly, ApiClientMethod $expected) {
    expect(ApiClientMethod::forRequest($httpMethod, $readOnly))->toBe($expected);
})->with([
    ['GET', false, ApiClientMethod::Get],
    ['HEAD', false, ApiClientMethod::Get],
    ['OPTIONS', false, ApiClientMethod::Get],
    ['POST', false, ApiClientMethod::Post],
    ['POST', true, ApiClientMethod::Get],
    ['PUT', false, ApiClientMethod::Put],
    ['PATCH', false, ApiClientMethod::Put],
    ['DELETE', false, ApiClientMethod::Delete],
]);

it('lists only existing POST routes as read-only or always allowed', function () {
    $prefix = trim((string) config('external-api.prefix'), '/');
    $postUris = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => in_array('POST', $route->methods(), true))
        ->map(fn (LaravelRoute $route): string => $route->uri())
        ->all();

    foreach ([...config('external-api.method_gate.read_only_post_routes'), ...config('external-api.method_gate.always_allowed_routes')] as $uri) {
        expect($postUris)->toContain("{$prefix}/{$uri}");
    }
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('rejects the documentation endpoints without login', function (string $endpoint) {
    $this->getJson("/api/api-clients/docs/{$endpoint}")->assertUnauthorized();
})->with(['openapi', 'postman']);

it('forbids the documentation endpoints without api-clients.view', function (string $endpoint) {
    Sanctum::actingAs(apiClientAdmin(['create']));

    $this->getJson("/api/api-clients/docs/{$endpoint}")->assertForbidden();
})->with(['openapi', 'postman']);

it('serves the OpenAPI document with the bearer scheme and the server url', function () {
    seedApiDocumentCache();
    Sanctum::actingAs(apiClientAdmin(['view']));

    $this->getJson('/api/api-clients/docs/openapi')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('info.version', config('external-api.version'))
        ->assertJsonPath('servers.0.url', rtrim(config('app.url'), '/').'/api')
        ->assertJsonPath('security.0.bearer', [])
        ->assertJsonPath('components.securitySchemes.bearer.scheme', 'bearer')
        ->assertJsonPath('paths./auth/client-login.post.operationId', fn ($id) => is_string($id));
});

it('describes the two client modes in the document description', function () {
    $description = generatedApiDocument()['info']['description'];

    expect($description)->toContain('Client key')->toContain('client-login');
});

// AC-012
it('serves the Postman collection with variables, bearer auth and the user_token script', function () {
    seedApiDocumentCache();
    Sanctum::actingAs(apiClientAdmin(['view']));

    $response = $this->getJson('/api/api-clients/docs/postman')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="qnet-api.postman_collection.json"')
        ->assertJsonPath('auth.bearer.0.value', '{{api_key}}');

    $collection = $response->json();
    $variables = array_column($collection['variable'], 'key');
    $requests = collect($collection['item'])->flatMap(fn (array $folder) => $folder['item']);
    $login = $requests->first(fn (array $item) => $item['request']['method'] === 'POST'
        && str_ends_with($item['request']['url']['raw'], '/auth/client-login'));
    $operations = collect(generatedApiDocument()['paths'])
        ->sum(fn (array $path) => count(array_intersect(array_keys($path), ['get', 'put', 'post', 'delete', 'patch', 'head', 'options'])));

    expect($variables)->toBe(['base_url', 'api_key', 'user_token'])
        ->and($requests)->toHaveCount($operations)
        ->and($login['event'][0]['listen'])->toBe('test')
        ->and(implode("\n", $login['event'][0]['script']['exec']))
        ->toContain('pm.response.json()')
        ->toContain("pm.collectionVariables.set('user_token'")
        ->and(json_decode($login['request']['body']['raw'], true))->toHaveKeys(['email', 'password']);
});

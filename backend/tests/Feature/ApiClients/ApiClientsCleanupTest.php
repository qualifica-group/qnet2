<?php

use Illuminate\Support\Facades\Route;

// AC-001
it('exposes no external v1 layer and no scope catalogue', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map->uri();

    expect($uris->filter(fn (string $uri) => str_contains($uri, 'v1/external')))->toBeEmpty()
        ->and($uris->contains('api/api-clients/scopes'))->toBeFalse()
        ->and($uris->contains('api/auth/client-login'))->toBeTrue()
        ->and(config('external-api.scopes'))->toBeNull()
        ->and(config('external-api.service_users.email_domain'))->toBeString()
        ->and(config('external-api.user_tokens.ttl_minutes'))->toBe(1440);
});

it('removed the classes of the external layer', function (string $class) {
    expect(class_exists($class))->toBeFalse();
})->with([
    'App\Http\Middleware\EnforceTokenActorBoundary',
    'App\Http\Controllers\External\V1\LeadController',
    'App\Services\ExternalApi\Docs\ExternalScopeOperationExtension',
    'App\Services\ExternalApi\Docs\ExternalEnvelopeInferExtension',
]);

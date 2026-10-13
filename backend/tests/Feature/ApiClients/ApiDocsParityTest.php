<?php

use App\Services\ApiDocs\OpenApiDocumentProvider;
use Dedoc\Scramble\Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const DOCUMENTED_METHODS = ['get', 'put', 'post', 'delete', 'patch'];

/**
 * Normalises a path so route parameters of different names compare equal.
 */
function normalizedApiPath(string $path): string
{
    return '/'.trim(preg_replace('/\{[^}]+\}/', '{}', $path), '/');
}

function isExcludedFromDocs(string $uri): bool
{
    foreach (config('external-api.docs.excluded_prefixes') as $excluded) {
        $pattern = config('external-api.prefix').'/'.$excluded;

        if (Str::is([$pattern, $pattern.'/*'], $uri)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array<int, string> "METHOD /path" of every documented operation
 */
function documentedOperations(array $document): array
{
    $operations = [];

    foreach ($document['paths'] as $path => $item) {
        foreach (array_intersect(array_keys($item), DOCUMENTED_METHODS) as $method) {
            $operations[] = strtoupper($method).' '.normalizedApiPath('/api'.$path);
        }
    }

    sort($operations);

    return $operations;
}

/**
 * @return array<int, string> "METHOD /path" of every router route that must be documented
 */
function expectedOperations(): array
{
    $prefix = config('external-api.prefix');
    $operations = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var LaravelRoute $route */
        if (! str_starts_with($route->uri(), $prefix.'/') || isExcludedFromDocs($route->uri())) {
            continue;
        }

        // Scramble documents one operation per route: PUT when a route answers both PUT and PATCH.
        $ignored = in_array('PUT', $route->methods(), true) ? ['HEAD', 'PATCH'] : ['HEAD'];

        foreach (array_diff($route->methods(), $ignored) as $method) {
            $operations[] = $method.' '.normalizedApiPath($route->uri());
        }
    }

    $operations = array_values(array_unique($operations));
    sort($operations);

    return $operations;
}

// AC-011
it('documents exactly the registered api routes minus the excluded prefixes', function () {
    expect(documentedOperations(generatedApiDocument()))->toBe(expectedOperations());
});

it('documents client-login and none of the app-only endpoints', function () {
    $operations = documentedOperations(generatedApiDocument());

    expect($operations)->toContain('POST /api/auth/client-login')
        ->toContain('POST /api/auth/logout')
        ->and(collect($operations)->filter(fn (string $operation) => collect([
            '/api/migrations', '/api/auth/login', '/api/auth/impersonation', '/api/auth/stop-impersonation',
            '/api/presence', '/api/auth/me', '/api/config', '/api/system-health', '/impersonate',
        ])->contains(fn (string $excluded) => str_contains($operation, $excluded)))->all())->toBeEmpty();
});

it('infers the envelope of the controller helpers with the resource as data', function () {
    $schema = generatedApiDocument()['paths']['/leads/{lead}']['get']['responses']['200']['content']['application/json']['schema'];

    expect($schema['properties']['data'])->toBe(['$ref' => '#/components/schemas/LeadResource'])
        ->and($schema['properties'])->toHaveKeys(['success', 'message', 'data', 'permissions']);
});

it('picks up a route added at runtime without any cache intervention', function () {
    seedApiDocumentCache();
    $provider = app(OpenApiDocumentProvider::class);
    $before = $provider->signature();

    expect(documentedOperations($provider->cached()))->not->toContain('GET /api/docs-parity-fixture');

    Route::get('api/docs-parity-fixture', fn () => response()->json(['ok' => true]));

    expect($provider->signature())->not->toBe($before)
        ->and($provider->cached())->toBeNull()
        ->and(documentedOperations($provider->generate()))->toContain('GET /api/docs-parity-fixture');
});

it('warms the document cache so the provider serves it without regenerating', function () {
    $this->artisan('api-docs:warm')->assertSuccessful();

    $key = OpenApiDocumentProvider::CACHE_KEY_PREFIX.app(OpenApiDocumentProvider::class)->signature();
    expect(Cache::has($key))->toBeTrue();

    app()->bind(Generator::class, fn () => throw new LogicException('document regenerated'));

    expect(app(OpenApiDocumentProvider::class)->cached())->toBe(Cache::get($key));
});

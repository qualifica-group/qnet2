<?php

use App\Jobs\GenerateApiDocsJob;
use App\Services\ApiDocs\OpenApiDocumentProvider;
use Dedoc\Scramble\Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(OpenApiDocumentProvider::CACHE_KEY);
    Cache::forget(OpenApiDocumentProvider::GENERATING_KEY);
    Cache::lock(GenerateApiDocsJob::LOCK_KEY)->forceRelease();
});

function bindFakeGenerator(): object
{
    $fake = new class
    {
        public int $calls = 0;

        public function __invoke(): array
        {
            $this->calls++;

            return ['openapi' => '3.1.0', 'paths' => []];
        }
    };
    app()->instance(Generator::class, $fake);

    return $fake;
}

it('never generates on read when no document exists yet', function (string $endpoint) {
    Queue::fake();
    config(['queue.default' => 'database']);
    app()->bind(Generator::class, fn () => throw new LogicException('generated in-request'));
    Sanctum::actingAs(apiClientAdmin(['view']));

    $this->getJson("/api/api-clients/docs/{$endpoint}")
        ->assertNotFound()
        ->assertExactJson([
            'success' => false,
            'message' => 'The documentation has not been generated yet.',
            'data' => ['status' => 'missing', 'generated_at' => null],
        ]);

    Queue::assertNothingPushed();
})->with(['openapi', 'postman']);

it('serves the stored document without dispatching anything', function (string $endpoint) {
    Queue::fake();
    seedApiDocumentCache();
    Sanctum::actingAs(apiClientAdmin(['view']));

    $this->getJson("/api/api-clients/docs/{$endpoint}")->assertOk();

    Queue::assertNothingPushed();
})->with(['openapi', 'postman']);

it('reports the status of the document', function () {
    Sanctum::actingAs(apiClientAdmin(['view']));

    $this->getJson('/api/api-clients/docs/status')
        ->assertOk()
        ->assertJsonPath('data', ['status' => 'missing', 'generated_at' => null]);

    seedApiDocumentCache('2026-11-13T00:00:00+00:00');

    $this->getJson('/api/api-clients/docs/status')
        ->assertOk()
        ->assertJsonPath('data', ['status' => 'ready', 'generated_at' => '2026-11-13T00:00:00+00:00']);
});

it('starts a background regeneration on refresh and reports it as generating', function () {
    Queue::fake();
    config(['queue.default' => 'database']);
    seedApiDocumentCache('2026-11-13T00:00:00+00:00');
    Sanctum::actingAs(apiClientAdmin(['view', 'update']));

    $this->postJson('/api/api-clients/docs/refresh')
        ->assertStatus(202)
        ->assertJsonPath('data', ['status' => 'generating', 'generated_at' => '2026-11-13T00:00:00+00:00']);

    Queue::assertPushed(GenerateApiDocsJob::class);
    $this->getJson('/api/api-clients/docs/status')->assertJsonPath('data.status', 'generating');
    // The previous document keeps being served while the new one is generated
    $this->getJson('/api/api-clients/docs/openapi')->assertOk();
});

it('defers the refresh after the response with the sync queue', function () {
    Bus::fake();
    config(['queue.default' => 'sync']);
    Sanctum::actingAs(apiClientAdmin(['view', 'update']));

    $this->postJson('/api/api-clients/docs/refresh')->assertStatus(202);

    Bus::assertDispatchedAfterResponse(GenerateApiDocsJob::class);
});

it('requires the view permission to read and the update permission to refresh', function () {
    Queue::fake();
    Sanctum::actingAs(apiClientAdmin(['create']));
    $this->getJson('/api/api-clients/docs/openapi')->assertForbidden();
    $this->getJson('/api/api-clients/docs/status')->assertForbidden();

    Sanctum::actingAs(apiClientAdmin(['view']));
    $this->postJson('/api/api-clients/docs/refresh')->assertForbidden();

    Queue::assertNothingPushed();
    expect(Cache::has(OpenApiDocumentProvider::GENERATING_KEY))->toBeFalse();
});

it('does not regenerate while another generation holds the lock', function () {
    $fake = bindFakeGenerator();
    $lock = Cache::lock(GenerateApiDocsJob::LOCK_KEY, 60);
    $lock->get();

    (new GenerateApiDocsJob)->handle(app(OpenApiDocumentProvider::class));
    $lock->release();

    expect($fake->calls)->toBe(0)
        ->and(app(OpenApiDocumentProvider::class)->cached())->toBeNull();
});

it('stores the document in the job and clears the generating flag', function () {
    $fake = bindFakeGenerator();
    Cache::put(OpenApiDocumentProvider::GENERATING_KEY, true, 600);
    $this->travelTo('2026-11-13 00:00:00');

    (new GenerateApiDocsJob)->handle(app(OpenApiDocumentProvider::class));

    expect($fake->calls)->toBe(1);
    Sanctum::actingAs(apiClientAdmin(['view']));
    $this->getJson('/api/api-clients/docs/openapi')->assertOk()->assertJsonPath('openapi', '3.1.0');
    $this->getJson('/api/api-clients/docs/status')
        ->assertJsonPath('data', ['status' => 'ready', 'generated_at' => now()->toIso8601String()]);
});

it('clears the generating flag when the generation fails', function () {
    app()->bind(Generator::class, fn () => throw new RuntimeException('scramble failed'));
    Cache::put(OpenApiDocumentProvider::GENERATING_KEY, true, 600);

    expect(fn () => app(OpenApiDocumentProvider::class)->generate())->toThrow(RuntimeException::class);
    expect(app(OpenApiDocumentProvider::class)->status()['status'])->toBe('missing');
});

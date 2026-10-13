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
    Cache::forget(OpenApiDocumentProvider::CACHE_KEY_PREFIX.app(OpenApiDocumentProvider::class)->signature());
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

it('never runs the generator inside the request when the cache is cold', function (string $endpoint) {
    Queue::fake();
    config(['queue.default' => 'database']);
    app()->bind(Generator::class, fn () => throw new LogicException('generated in-request'));
    Sanctum::actingAs(apiClientAdmin(['view']));

    $this->getJson("/api/api-clients/docs/{$endpoint}")
        ->assertStatus(202)
        ->assertHeader('Retry-After', '5')
        ->assertExactJson([
            'success' => true,
            'message' => 'The documentation is being prepared, try again in a few seconds.',
            'data' => ['status' => 'generating'],
        ]);

    Queue::assertPushed(GenerateApiDocsJob::class);
})->with(['openapi', 'postman']);

it('defers the generation after the response with the sync queue', function () {
    Bus::fake();
    config(['queue.default' => 'sync']);
    Sanctum::actingAs(apiClientAdmin(['view']));

    $this->getJson('/api/api-clients/docs/openapi')->assertStatus(202);

    Bus::assertDispatchedAfterResponse(GenerateApiDocsJob::class);
});

it('serves the cached document without dispatching anything', function (string $endpoint) {
    Queue::fake();
    seedApiDocumentCache();
    Sanctum::actingAs(apiClientAdmin(['view']));

    $this->getJson("/api/api-clients/docs/{$endpoint}")->assertOk();

    Queue::assertNothingPushed();
})->with(['openapi', 'postman']);

it('keeps the view permission on the cold path', function () {
    Queue::fake();
    Sanctum::actingAs(apiClientAdmin(['create']));

    $this->getJson('/api/api-clients/docs/openapi')->assertForbidden();

    Queue::assertNothingPushed();
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

it('fills the cache in the job so the next request answers 200', function () {
    $fake = bindFakeGenerator();

    (new GenerateApiDocsJob)->handle(app(OpenApiDocumentProvider::class));

    expect($fake->calls)->toBe(1);
    Sanctum::actingAs(apiClientAdmin(['view']));
    $this->getJson('/api/api-clients/docs/openapi')->assertOk()->assertJsonPath('openapi', '3.1.0');
});

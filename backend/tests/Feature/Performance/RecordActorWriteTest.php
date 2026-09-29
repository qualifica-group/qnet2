<?php

use App\Models\User;
use App\Support\Cache\AggregateCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->prefix('api')->group(function () {
        Route::match(['post', 'put', 'patch', 'delete', 'get'], 'probe-ok', fn () => response()->json(['ok' => true]));
        Route::post('probe-fail', fn () => response()->json(['ok' => false], 422));
        Route::post('tables/{domain}/rows', fn () => response()->json([]));
        Route::post('tables/{domain}/values', fn () => response()->json([]));
        Route::post('request-management/form-context', fn () => response()->json([]));
        Route::post('enrollee-management/form-context', fn () => response()->json([]));
    });
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function actorWriteRecorded(User $user): bool
{
    return Cache::has('aggregates:actor-wrote:'.$user->getKey());
}

it('AC-016 records the write on successful POST/PUT/PATCH/DELETE', function (string $method) {
    $this->json($method, '/api/probe-ok')->assertOk();

    expect(actorWriteRecorded($this->user))->toBeTrue();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('AC-016 does not record on GET or on a 4xx response', function () {
    $this->getJson('/api/probe-ok')->assertOk();
    expect(actorWriteRecorded($this->user))->toBeFalse();

    $this->postJson('/api/probe-fail')->assertStatus(422);
    expect(actorWriteRecorded($this->user))->toBeFalse();
});

it('AC-016 does not record on read-only POSTs', function (string $uri) {
    $this->postJson($uri)->assertOk();

    expect(actorWriteRecorded($this->user))->toBeFalse();
})->with([
    '/api/tables/quotes/rows',
    '/api/tables/quotes/values',
    '/api/request-management/form-context',
    '/api/enrollee-management/form-context',
]);

it('AC-016 makes the actor read a recomputed value after a write', function () {
    $cache = app(AggregateCache::class);
    $cache->remember('k', fn () => 'old');
    $this->travel(2)->seconds();

    $this->postJson('/api/probe-ok')->assertOk();

    expect($cache->remember('k', fn () => 'new', $this->user))->toBe('new');
});

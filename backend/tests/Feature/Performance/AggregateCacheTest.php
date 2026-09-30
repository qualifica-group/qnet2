<?php

use App\Models\User;
use App\Support\Cache\AggregateCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

function counting(array &$calls, mixed $value): Closure
{
    return function () use (&$calls, $value) {
        $calls[] = $value;

        return $value;
    };
}

function runDeferred(): void
{
    app(DeferredCallbackCollection::class)->invoke();
}

beforeEach(function () {
    config([
        'aggregate-cache.fresh_seconds' => 10,
        'aggregate-cache.stale_seconds' => 300,
        'aggregate-cache.retain_seconds' => 3600,
        'aggregate-cache.lock_wait_seconds' => 0,
    ]);
    Cache::flush();
    $this->cache = app(AggregateCache::class);
});

it('AC-010 computes once on a missing key and serves the second read from cache', function () {
    $calls = [];

    expect($this->cache->remember('k', counting($calls, ['a' => 1])))->toBe(['a' => 1])
        ->and($this->cache->remember('k', counting($calls, ['a' => 2])))->toBe(['a' => 1])
        ->and($calls)->toHaveCount(1);
});

it('AC-010 caches falsy scalars and null as values', function () {
    $calls = [];

    $this->cache->remember('zero', counting($calls, 0));
    $this->cache->remember('zero', counting($calls, 1));
    $this->cache->remember('nil', counting($calls, null));
    $this->cache->remember('nil', counting($calls, 1));

    expect($calls)->toHaveCount(2);
});

it('AC-011 between fresh and stale serves the old value and refreshes once after the response', function () {
    $calls = [];
    $this->cache->remember('k', counting($calls, 'old'));

    $this->travel(60)->seconds();

    expect($this->cache->remember('k', counting($calls, 'new')))->toBe('old')
        ->and($calls)->toBe(['old']);

    runDeferred();

    expect($calls)->toBe(['old', 'new'])
        ->and($this->cache->remember('k', counting($calls, 'newer')))->toBe('new')
        ->and($calls)->toHaveCount(2);
});

it('AC-011 lets only one refresher recompute while the refresh lock is held', function () {
    $calls = [];
    $this->cache->remember('k', counting($calls, 'old'));
    $this->travel(60)->seconds();

    $other = Cache::lock('aggregates:lock:aggregates:k', 30);
    expect($other->get())->toBeTrue();

    expect($this->cache->remember('k', counting($calls, 'new')))->toBe('old');
    runDeferred();

    expect($calls)->toBe(['old']);
});

it('AC-012 beyond stale_seconds recomputes synchronously and returns the new value', function () {
    $calls = [];
    $this->cache->remember('k', counting($calls, 'old'));

    $this->travel(301)->seconds();

    expect($this->cache->remember('k', counting($calls, 'new')))->toBe('new')
        ->and($calls)->toBe(['old', 'new']);
});

it('AC-013 returns the value stored by the lock holder without running its own compute', function () {
    config(['aggregate-cache.lock_wait_seconds' => 5]);
    $holder = Cache::lock('aggregates:lock:aggregates:k', 30);
    $holder->get();

    Sleep::fake();
    Sleep::whenFakingSleep(function () use ($holder) {
        Cache::put('aggregates:k', ['value' => 'from-holder', 'computed_at' => now()->getTimestampMs()], 300);
        $holder->release();
    });

    $calls = [];

    expect($this->cache->remember('k', counting($calls, 'mine')))->toBe('from-holder')
        ->and($calls)->toBe([]);
});

it('AC-014 computes anyway when the lock is not obtained within the wait and no value exists', function () {
    Cache::lock('aggregates:lock:aggregates:k', 30)->get();
    $calls = [];

    expect($this->cache->remember('k', counting($calls, 'mine')))->toBe('mine')
        ->and($calls)->toBe(['mine']);
});

it('AC-014 serves the retained value, without computing, when the lock wait times out', function () {
    $calls = [];
    $this->cache->remember('k', counting($calls, 'old'));
    $this->travel(400)->seconds();
    Cache::lock('aggregates:lock:aggregates:k', 30)->get();

    expect($this->cache->remember('k', counting($calls, 'mine')))->toBe('old')
        ->and($calls)->toBe(['old']);
});

it('AC-014 computes on lock timeout for an actor who wrote after the stored value', function () {
    $actor = User::factory()->create();
    $calls = [];
    $this->cache->remember('k', counting($calls, 'old'));
    $this->travel(2)->seconds();
    $this->cache->recordWrite($actor);
    Cache::lock('aggregates:lock:aggregates:k', 30)->get();

    expect($this->cache->remember('k', counting($calls, 'exact'), $actor))->toBe('exact')
        ->and($calls)->toBe(['old', 'exact']);
});

it('AC-012 recomputes inline an entry older than stale but still retained, when the lock is free', function () {
    $calls = [];
    $this->cache->remember('k', counting($calls, 'old'));
    $this->travel(1000)->seconds();

    expect(Cache::get('aggregates:k'))->not->toBeNull()
        ->and($this->cache->remember('k', counting($calls, 'new')))->toBe('new')
        ->and($calls)->toBe(['old', 'new']);
});

it('AC-015 rejects objects, also nested, and does not swallow the error', function (mixed $value) {
    $this->cache->remember('k', fn () => $value);
})->with([
    'model' => fn () => new User,
    'collection' => fn () => collect([1]),
    'nested object' => fn () => ['rows' => [['x' => new stdClass]]],
])->throws(InvalidArgumentException::class);

it('AC-015 round-trips arrays identically through the database store', function () {
    config(['cache.default' => 'database']);
    $value = ['tabs' => [['id' => 1, 'count' => 3, 'label' => 'A'], ['id' => 2, 'count' => 0, 'label' => null]], 'total' => 3];
    $calls = [];

    $this->cache->remember('tabs', counting($calls, $value));
    $read = $this->cache->remember('tabs', counting($calls, ['x']));

    expect($read)->toBe($value)->and($calls)->toHaveCount(1);
});

it('AC-017 recomputes synchronously for an actor who wrote after computed_at, not for others', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $calls = [];
    $this->cache->remember('k', counting($calls, 'old'));

    $this->travel(2)->seconds();
    $this->cache->recordWrite($actor);

    expect($this->cache->remember('k', counting($calls, 'other-read'), $other))->toBe('old')
        ->and($this->cache->remember('k', counting($calls, 'exact'), $actor))->toBe('exact')
        ->and($this->cache->remember('k', counting($calls, 'later'), $actor))->toBe('exact')
        ->and($this->cache->remember('k', counting($calls, 'later'), $other))->toBe('exact');
});

it('AC-029 falls back to the computed value and logs when the store throws', function () {
    Log::spy();
    Cache::shouldReceive('get')->andThrow(new RuntimeException('store down'));

    $calls = [];

    expect($this->cache->remember('k', counting($calls, ['v' => 1])))->toBe(['v' => 1]);
    Log::shouldHaveReceived('warning')->atLeast()->once();
});

it('AC-029 falls back when lock and put throw', function () {
    Log::spy();
    Cache::shouldReceive('get')->andReturn(null);
    Cache::shouldReceive('lock')->andThrow(new RuntimeException('lock down'));
    Cache::shouldReceive('put')->andThrow(new RuntimeException('put down'));

    expect($this->cache->remember('k', fn () => 7))->toBe(7);
});

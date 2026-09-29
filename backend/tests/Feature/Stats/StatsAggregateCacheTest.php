<?php

use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Stats panel behind AggregateCache (spec 0178, D-3/D-4)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    config(['aggregate-cache.fresh_seconds' => 60, 'aggregate-cache.stale_seconds' => 300]);
    Cache::flush();
});

/** Counts the queries that touch the given table while $callback runs. */
function statsQueriesOn(string $table, Closure $callback): int
{
    $count = 0;
    DB::listen(function ($query) use (&$count, $table) {
        if (str_contains($query->sql, $table)) {
            $count++;
        }
    });
    $callback();

    return $count;
}

it('AC-022 serves a global domain to a second actor without recomputing', function () {
    Registry::factory()->count(2)->create();

    Sanctum::actingAs(statsUserWith(['registries']));
    $first = statsQueriesOn('"registries"', fn () => $this->getJson('/api/stats/registries')->assertOk());

    Sanctum::actingAs(statsUserWith(['registries']));
    $second = statsQueriesOn('"registries"', fn () => $this->getJson('/api/stats/registries')->assertOk());

    expect($first)->toBeGreaterThan(0)->and($second)->toBe(0);
});

it('AC-022 never shares a per-actor panel (tasks) between two actors', function () {
    $a = taskActorWith(['viewAny', 'view'], withViewAll: false);
    $b = taskActorWith(['viewAny', 'view'], withViewAll: false);
    Task::factory()->forCreator($a)->count(2)->create(['estimated_minutes' => 10]);
    Task::factory()->forCreator($b)->count(3)->create(['estimated_minutes' => 10]);

    $estimated = function (User $actor): int {
        Sanctum::actingAs($actor);
        $widgets = collect($this->getJson('/api/stats/tasks')->assertOk()->json('data.widgets'))->keyBy('key');

        return $widgets['estimated_minutes']['value'];
    };

    expect($estimated($a))->toBe(20)->and($estimated($b))->toBe(30)->and($estimated($a))->toBe(20);
});

it('AC-022 keys the panel by domain and scope', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);
    $keys = [];
    Event::listen(KeyWritten::class, function ($event) use (&$keys) {
        $keys[] = $event->key;
    });

    $this->getJson('/api/stats/tasks')->assertOk();

    expect($keys)->toContain('aggregates:stats:tasks:user:'.$actor->id.':'.app()->getLocale());
});

it('AC-022 keys the panel by locale, so translated labels never cross languages', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);
    $keys = [];
    Event::listen(KeyWritten::class, function ($event) use (&$keys) {
        $keys[] = $event->key;
    });

    $this->getJson('/api/stats/tasks', ['Accept-Language' => 'it'])->assertOk();
    $this->getJson('/api/stats/tasks', ['Accept-Language' => 'en'])->assertOk();

    expect($keys)->toContain('aggregates:stats:tasks:user:'.$actor->id.':it')
        ->and($keys)->toContain('aggregates:stats:tasks:user:'.$actor->id.':en');
});

it('AC-022 returns the same JSON cached and uncached', function () {
    Registry::factory()->count(3)->create();
    Sanctum::actingAs(statsUserWith(['registries']));

    $cold = $this->getJson('/api/stats/registries')->assertOk()->getContent();
    $warm = $this->getJson('/api/stats/registries')->assertOk()->getContent();

    expect($warm)->toBe($cold);
});

it('AC-029 answers 200 with the same payload when the cache store throws (stats)', function () {
    Registry::factory()->count(2)->create();
    Sanctum::actingAs(statsUserWith(['registries']));
    $expected = $this->getJson('/api/stats/registries')->assertOk()->getContent();

    Cache::flush();
    Cache::shouldReceive('get')->andThrow(new RuntimeException('store down'));

    expect($this->getJson('/api/stats/registries')->assertOk()->getContent())->toBe($expected);
});

it('AC-022 still answers 403 without viewAny and never touches the cache', function () {
    $keys = [];
    Event::listen([CacheHit::class, CacheMissed::class, KeyWritten::class], function ($event) use (&$keys) {
        $keys[] = $event->key;
    });
    Sanctum::actingAs(statsUserWith([]));

    $this->getJson('/api/stats/registries')->assertForbidden();

    expect(collect($keys)->filter(fn ($k) => str_starts_with($k, 'aggregates:')))->toBeEmpty();
});

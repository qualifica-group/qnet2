<?php

use App\Models\Registry;
use App\Models\Source;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\User;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/tables/{domain}/values behind AggregateCache (spec 0178, D-3)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    config(['aggregate-cache.fresh_seconds' => 60, 'aggregate-cache.stale_seconds' => 300]);
    Cache::flush();
});

function valuesRegistryActor()
{
    Permission::findOrCreate('registries.viewAny');

    return User::factory()->create()->givePermissionTo('registries.viewAny');
}

it('AC-023 keeps distinct results for different filterModel, search and limit', function () {
    Sanctum::actingAs(valuesRegistryActor());
    $web = Source::factory()->create(['name' => 'Web']);
    $fair = Source::factory()->create(['name' => 'Fair']);
    Registry::factory()->create(['source_id' => $web->id, 'is_supplier' => true]);
    Registry::factory()->create(['source_id' => $fair->id, 'is_supplier' => false]);

    $all = $this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk()->json('data.values');
    $searched = $this->postJson('/api/tables/registries/values', ['columnId' => 'source', 'search' => 'Fa'])->assertOk()->json('data.values');
    $limited = $this->postJson('/api/tables/registries/values', ['columnId' => 'source', 'limit' => 1])->assertOk()->json('data');
    $filtered = $this->postJson('/api/tables/registries/values', [
        'columnId' => 'source',
        'filterModel' => ['is_supplier' => ['filterType' => 'boolean', 'filter' => true]],
    ])->assertOk()->json('data.values');

    expect($all)->toEqualCanonicalizing(['Web', 'Fair'])
        ->and($searched)->toBe(['Fair'])
        ->and($limited['values'])->toHaveCount(1)->and($limited['hasMore'])->toBeTrue()
        ->and($filtered)->toBe(['Web']);
});

it('AC-023 does not share an entry between two actors with different visibility', function () {
    $a = taskActorWith(['viewAny', 'view'], withViewAll: false);
    $b = taskActorWith(['viewAny', 'view'], withViewAll: false);
    $mine = TaskPriority::factory()->create(['name' => 'Alta']);
    $theirs = TaskPriority::factory()->create(['name' => 'Bassa']);
    Task::factory()->forCreator($a)->create(['task_priority_id' => $mine->id]);
    Task::factory()->forCreator($b)->create(['task_priority_id' => $theirs->id]);

    $values = function ($actor) {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/tables/tasks/values', ['columnId' => 'task_priority', 'limit' => 25])
            ->assertOk()->json('data.values');
    };

    expect($values($a))->toBe(['Alta'])->and($values($b))->toBe(['Bassa'])->and($values($a))->toBe(['Alta']);
});

it('AC-023 serves the second identical request from the cache', function () {
    Sanctum::actingAs(valuesRegistryActor());
    $written = 0;
    Event::listen(KeyWritten::class, function ($event) use (&$written) {
        $written += str_starts_with($event->key, 'aggregates:values:user:') ? 1 : 0;
    });

    $this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk();
    $this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk();

    expect($written)->toBe(1);
});

it('AC-023 a 422 validation error neither reads nor writes the cache', function () {
    Sanctum::actingAs(valuesRegistryActor());
    $keys = [];
    Event::listen([CacheHit::class, CacheMissed::class, KeyWritten::class], function ($event) use (&$keys) {
        $keys[] = $event->key;
    });

    $this->postJson('/api/tables/registries/values', ['columnId' => 'nope'])->assertUnprocessable();

    expect(collect($keys)->filter(fn ($k) => str_starts_with($k, 'aggregates:')))->toBeEmpty();
});

it('AC-023 returns the same JSON cached and uncached', function () {
    Sanctum::actingAs(valuesRegistryActor());
    Registry::factory()->create(['source_id' => Source::factory()->create(['name' => 'Web'])->id]);

    $cold = $this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk()->getContent();
    $warm = $this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk()->getContent();

    expect($warm)->toBe($cold);
});

it('AC-029 answers 200 with the same payload when the cache store throws (values)', function () {
    Sanctum::actingAs(valuesRegistryActor());
    Registry::factory()->create(['source_id' => Source::factory()->create(['name' => 'Web'])->id]);
    $expected = $this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk()->getContent();

    Cache::flush();
    Cache::shouldReceive('get')->andThrow(new RuntimeException('store down'));

    expect($this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk()->getContent())->toBe($expected);
});

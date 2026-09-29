<?php

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
| Spec 0178, D-1: `knownTotal` lets later pages skip count() and aggregates().
| `tasks` is used because it overrides aggregates() (spec 0156).
*/

function knownTotalTasksActor(int $rows): void
{
    $actor = taskActorWith(['viewAny', 'view']);
    Task::factory()->count($rows)->forCreator($actor)->create();
    Sanctum::actingAs($actor);
}

/**
 * @return array<int, string>
 */
// The row SELECT embeds count(*)/sum() subselects, so only a query that STARTS with the aggregate is a real count/aggregate.
function knownTotalQueries(Closure $callback): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });
    $callback();

    return $queries;
}

it('AC-001: startRow=0 ignores knownTotal and counts', function () {
    knownTotalTasksActor(12);
    $response = null;

    $queries = knownTotalQueries(function () use (&$response): void {
        $response = $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => ['visible']], 'knownTotal' => 5,
        ])->assertOk();
    });

    expect($response->json('pagination.total'))->toBe(12)
        ->and(collect($queries)->contains(fn ($sql) => str_starts_with($sql, 'select count(*) as "aggregate"')))->toBeTrue();
});

it('AC-002: startRow>0 with knownTotal skips count and aggregates and omits meta', function () {
    knownTotalTasksActor(3);
    $response = null;

    $queries = knownTotalQueries(function () use (&$response): void {
        $response = $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 25, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']], 'knownTotal' => 60,
        ])->assertOk();
    });

    expect($response->json('pagination.total'))->toBe(60)
        ->and($response->json('pagination.total_pages'))->toBe(3)
        ->and($response->json())->not->toHaveKey('meta')
        ->and(collect($queries)->contains(fn ($sql) => str_starts_with($sql, 'select count(*) as "aggregate"')))->toBeFalse()
        ->and(collect($queries)->contains(fn ($sql) => str_starts_with($sql, 'select sum(')))->toBeFalse();
});

it('AC-003: startRow>0 without knownTotal counts and returns aggregates as before', function () {
    knownTotalTasksActor(3);
    $response = null;

    $queries = knownTotalQueries(function () use (&$response): void {
        $response = $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 25, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']],
        ])->assertOk();
    });

    expect($response->json('pagination.total'))->toBe(3)
        ->and($response->json('meta.aggregates'))->toHaveKey('estimated_minutes_total')
        ->and(collect($queries)->contains(fn ($sql) => str_starts_with($sql, 'select count(*) as "aggregate"')))->toBeTrue();
});

it('AC-004: an invalid knownTotal is rejected with 422', function (mixed $value) {
    knownTotalTasksActor(1);

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 25, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']], 'knownTotal' => $value,
    ])->assertStatus(422)->assertJsonValidationErrors('knownTotal');
})->with([-1, 'abc']);

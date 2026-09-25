<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Feature coverage for the `reports_to` grid column on `users` (spec 0166
 * D-9, AC-010), extracted into UserReportsToColumn: the column is AGGREGATED
 * over the `employment_profile_manager` pivot (multiple managers per
 * profile), unlike every other RELATED_NAME_COLUMNS entry in
 * UserEmploymentColumns. Split out of UserEmploymentColumnsTest.php purely
 * to stay under the file-size limit (engineering.md §6) — same actor helper,
 * redeclared `if (! function_exists())` so both files can load in the same
 * Pest run.
 */
if (! function_exists('reportsToTableActor')) {
    function reportsToTableActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('users.viewAny');

        return $user;
    }
}

if (! function_exists('rowsPayloadForReportsTo')) {
    function rowsPayloadForReportsTo(array $overrides = []): array
    {
        return array_merge(['startRow' => 0, 'endRow' => 25], $overrides);
    }
}

it('rows: reports_to is an array of {id, name} ordered by manager name, [] without any manager (spec 0166 AC-010)', function () {
    $actor = reportsToTableActor();
    $managerB = User::factory()->create(['name' => 'Bianchi']);
    $managerA = User::factory()->create(['name' => 'Andreoli']);
    $subordinate = User::factory()->withEmployment(fn ($f) => $f->reportsTo($managerB, $managerA))->create(['name' => 'Subordinate']);
    $noManager = User::factory()->withEmployment()->create(['name' => 'NoManager']);
    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/users/rows', rowsPayloadForReportsTo())->assertOk()->json('items'));

    expect($rows->firstWhere('id', $subordinate->id)['reports_to'])->toBe([
        ['id' => $managerA->id, 'name' => 'Andreoli'],
        ['id' => $managerB->id, 'name' => 'Bianchi'],
    ])
        ->and($rows->firstWhere('id', $noManager->id)['reports_to'])->toBe([]);
});

it('rows: the reports_to set filter matches a user when AT LEAST ONE manager name is among the picked values (spec 0166 AC-010)', function () {
    $actor = reportsToTableActor();
    $managerA = User::factory()->create(['name' => 'Andreoli']);
    $managerB = User::factory()->create(['name' => 'Bianchi']);
    $managerC = User::factory()->create(['name' => 'Colombo']);

    $matching = User::factory()->withEmployment(fn ($f) => $f->reportsTo($managerA, $managerB))->create(['name' => 'Matching']);
    $other = User::factory()->withEmployment(fn ($f) => $f->reportsTo($managerC))->create(['name' => 'Other']);
    $noManager = User::factory()->withEmployment()->create(['name' => 'NoManager']);
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/users/rows', rowsPayloadForReportsTo([
        'filterModel' => ['reports_to' => ['values' => ['Bianchi']]],
    ]))->assertOk()->json('items'))->pluck('id');

    expect($ids->all())->toBe([$matching->id])
        ->and($ids)->not->toContain($other->id)
        ->and($ids)->not->toContain($noManager->id);
});

it('rows: the reports_to blank entry ("(Vuoti)") matches users with no employment profile or no manager at all (spec 0166 AC-010)', function () {
    $actor = reportsToTableActor();
    $manager = User::factory()->create(['name' => 'Andreoli']);

    $withManager = User::factory()->withEmployment(fn ($f) => $f->reportsTo($manager))->create(['name' => 'WithManager']);
    $noManager = User::factory()->withEmployment()->create(['name' => 'NoManager']);
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/users/rows', rowsPayloadForReportsTo([
        'filterModel' => ['reports_to' => ['values' => [null]]],
    ]))->assertOk()->json('items'))->pluck('id');

    expect($ids)->toContain($noManager->id)
        ->and($ids)->toContain($actor->id) // the actor itself has no employment profile at all.
        ->and($ids)->not->toContain($withManager->id);
});

it('values: reports_to distinct values contain every manager name reachable through the pivot (spec 0166 AC-010)', function () {
    $actor = reportsToTableActor();
    $managerA = User::factory()->create(['name' => 'Andreoli']);
    $managerB = User::factory()->create(['name' => 'Bianchi']);
    User::factory()->withEmployment(fn ($f) => $f->reportsTo($managerA, $managerB))->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'reports_to'])->assertOk();

    // `null` first: the blank entry ("(Vuoti)") — the actor itself has none.
    expect($response->json('data.values'))->toBe([null, 'Andreoli', 'Bianchi']);
});

it('rows: sorting by reports_to orders on the first manager name alphabetically (MIN) without duplicating rows (spec 0166 AC-010)', function () {
    $actor = reportsToTableActor();
    $alpha = User::factory()->create(['name' => 'Alpha']);
    $zulu = User::factory()->create(['name' => 'Zulu']);

    // `Middle` reports to both: it must sort on `Alpha`, the first name of
    // its managers, not the last one nor the pivot insertion order.
    $first = User::factory()->withEmployment(fn ($f) => $f->reportsTo($alpha))->create(['name' => 'First']);
    $middle = User::factory()->withEmployment(fn ($f) => $f->reportsTo($zulu, $alpha))->create(['name' => 'Middle']);
    $last = User::factory()->withEmployment(fn ($f) => $f->reportsTo($zulu))->create(['name' => 'Last']);
    $noManager = User::factory()->withEmployment()->create(['name' => 'NoManager']);
    Sanctum::actingAs($actor);

    $ascending = $this->postJson('/api/tables/users/rows', rowsPayloadForReportsTo([
        'sortModel' => [['colId' => 'reports_to', 'sort' => 'asc']],
    ]))->assertOk()->json('items.*.id');

    // A user with no manager sorts NULL and stays in the page exactly once —
    // no row-multiplying JOIN duplicated it.
    expect(array_count_values($ascending)[$middle->id])->toBe(1)
        ->and($ascending)->toContain($noManager->id)
        ->and($ascending)->toContain($actor->id);

    $rank = fn (array $ids, int $id): int => (int) array_search($id, $ids, true);

    expect($rank($ascending, $first->id))->toBeLessThan($rank($ascending, $last->id))
        ->and($rank($ascending, $middle->id))->toBeLessThan($rank($ascending, $last->id));
});

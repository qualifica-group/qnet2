<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\TaskStatus;
use App\Models\User;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The Task resume status "in_progress" (spec 0116, D-4, AC-013/014/015)
|--------------------------------------------------------------------------
|
| RefreshDatabase runs every migration before each test — the equivalent of
| `migrate:fresh` — so 2026_09_11_100000_designate_in_progress_task_status
| has already promoted/created the row by the time a test body starts.
| QualificaTaskTaxonomySeeder is run explicitly wherever `--seed` matters
| (AC-013), the same idiom tests/Feature/Seeding/QualificaTaskTaxonomySeederTest.php
| already uses.
*/

if (! function_exists('taskResumeStatusActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskResumeStatusActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("task-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("task-statuses.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('inProgressStatus')) {
    function inProgressStatus(): TaskStatus
    {
        return TaskStatus::query()->where('system_key', TaskStatusSystemKey::InProgress->value)->firstOrFail();
    }
}

// ---------------------------------------------------------------------------
// AC-013 — migrate:fresh --seed lands on exactly one row, no duplicate name
// ---------------------------------------------------------------------------

it('AC-013: migrate:fresh --seed leaves exactly one in_progress row in the open phase, no duplicate name', function () {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    expect(TaskStatus::query()->where('system_key', TaskStatusSystemKey::InProgress->value)->count())->toBe(1);

    $status = inProgressStatus();

    expect($status->group)->toBe(TaskStatusGroup::Open)
        ->and(TaskStatus::query()->where('name', $status->name)->count())->toBe(1);
});

it('AC-013: re-running the taxonomy seeder stays idempotent on the resume status', function () {
    test()->seed(QualificaTaskTaxonomySeeder::class);
    test()->seed(QualificaTaskTaxonomySeeder::class);

    expect(TaskStatus::query()->where('system_key', TaskStatusSystemKey::InProgress->value)->count())->toBe(1)
        ->and(TaskStatus::query()->where('name', 'In corso')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-014 — the row is undeletable and its key survives a rename
// ---------------------------------------------------------------------------

it('AC-014: DELETE on the resume status is 422 from SystemStatusGuard, and the row survives', function () {
    Sanctum::actingAs(taskResumeStatusActorWith(['delete']));
    $status = inProgressStatus();

    $this->deleteJson("/api/task-statuses/{$status->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$status->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('task_statuses', ['id' => $status->id]);
});

it('AC-014: PATCH renames the resume status and the key stays', function () {
    Sanctum::actingAs(taskResumeStatusActorWith(['update']));
    $status = inProgressStatus();

    $this->patchJson("/api/task-statuses/{$status->id}", ['name' => 'Ripresa lavoro'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ripresa lavoro')
        ->assertJsonPath('data.system_key', 'in_progress');

    $this->assertDatabaseHas('task_statuses', [
        'id' => $status->id, 'name' => 'Ripresa lavoro', 'system_key' => 'in_progress',
    ]);
});

// ---------------------------------------------------------------------------
// AC-015 — reorder keeps in_progress pinned to the head, custom order kept
// ---------------------------------------------------------------------------

it('AC-015: a reorder keeps in_progress pinned at the head with the other protected rows, customs in the requested order', function () {
    Sanctum::actingAs(taskResumeStatusActorWith(['update']));
    $first = TaskStatus::factory()->create(['name' => 'Prima']);
    $second = TaskStatus::factory()->create(['name' => 'Seconda']);

    $response = $this->postJson('/api/task-statuses/reorder', [
        'ordered_ids' => [$second->id, $first->id],
    ])->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');

    $open = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Open->value)->firstOrFail();
    $inProgress = inProgressStatus();
    $closedPositive = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    $closedNegative = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedNegative->value)->firstOrFail();

    // Declared HEAD order (TaskStatus::SYSTEM_HEAD_KEYS): Open, then
    // InProgress — both pinned before every custom row.
    expect($open->fresh()->sort_order)->toBeLessThan($inProgress->fresh()->sort_order)
        ->and($inProgress->fresh()->sort_order)->toBeLessThan($rows[$second->id]['sort_order']);

    // Customs keep the requested relative order.
    expect($rows[$second->id]['sort_order'])->toBeLessThan($rows[$first->id]['sort_order']);

    // The two closing rows stay pinned after every custom row.
    expect($rows[$first->id]['sort_order'])->toBeLessThan($closedPositive->fresh()->sort_order)
        ->and($closedPositive->fresh()->sort_order)->toBeLessThan($closedNegative->fresh()->sort_order);
});

it('AC-015: the reorder set still excludes in_progress, so it cannot be moved as if it were a custom row', function () {
    Sanctum::actingAs(taskResumeStatusActorWith(['update']));
    $custom = TaskStatus::factory()->create();
    $inProgress = inProgressStatus();

    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => [$inProgress->id, $custom->id]])
        ->assertStatus(422);
});

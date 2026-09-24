<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Tasks\TaskInitialStatusResolver;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The "Assegnato" protected row and the creation-time derivation (spec 0118)
|--------------------------------------------------------------------------
|
| RefreshDatabase runs every migration before each test — the equivalent of
| `migrate:fresh` — so 2026_09_11_110000_designate_assigned_task_status has
| already inserted the protected row, already keyed, by the time a test body
| starts (AC-018). AC-017's OTHER path (a populated install promoting an
| already-seeded ordinary row) is exercised by replaying up() after
| reverting the row to look like the pre-migration state, the same idiom
| tests/Feature/RewardStatuses/RewardStatusReshapeMigrationTest.php uses.
*/

if (! function_exists('replayAssignedStatusPromotion')) {
    function replayAssignedStatusPromotion(): void
    {
        (require database_path('migrations/2026_09_11_110000_designate_assigned_task_status.php'))->up();
    }
}

if (! function_exists('rollbackAssignedStatusPromotion')) {
    function rollbackAssignedStatusPromotion(): void
    {
        (require database_path('migrations/2026_09_11_110000_designate_assigned_task_status.php'))->down();
    }
}

if (! function_exists('systemStatusIdFor')) {
    function systemStatusIdFor(TaskStatusSystemKey $key): int
    {
        return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
    }
}

if (! function_exists('assignedStatusActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function assignedStatusActorWith(array $abilities): User
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

// ---------------------------------------------------------------------------
// AC-017/AC-018 — the two migration paths
// ---------------------------------------------------------------------------

it('AC-018: migrate:fresh with no seeder already carries a protected "Assegnato" row', function () {
    $assigned = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Assigned->value)->firstOrFail();

    expect($assigned->name)->toBe('Assegnato')
        ->and($assigned->group)->toBe(TaskStatusGroup::Open)
        ->and($assigned->color)->toBe('blue')
        ->and($assigned->icon)->toBe('user')
        ->and($assigned->completion_percentage)->toBe(10);
});

it('AC-017: migrate promotes an already-seeded ordinary "Assegnato" row in place, keeping its own attributes', function () {
    $assigned = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Assigned->value)->firstOrFail();

    // Reverts the row to the pre-migration, populated-install state: an
    // ordinary row an admin has already recoloured/reordered.
    DB::table('task_statuses')->where('id', $assigned->id)->update([
        'system_key' => null,
        'color' => 'amber',
        'icon' => 'flag',
        'completion_percentage' => 30,
        'sort_order' => 999,
    ]);

    replayAssignedStatusPromotion();

    $row = DB::table('task_statuses')->where('id', $assigned->id)->first();

    expect($row->system_key)->toBe(TaskStatusSystemKey::Assigned->value)
        ->and($row->name)->toBe('Assegnato')
        ->and($row->color)->toBe('amber')
        ->and($row->icon)->toBe('flag')
        ->and($row->completion_percentage)->toBe(30)
        ->and($row->sort_order)->toBe(999);
});

it('AC-022: rolling back the migration clears the system_key but keeps the row', function () {
    $assigned = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Assigned->value)->firstOrFail();

    rollbackAssignedStatusPromotion();

    $row = DB::table('task_statuses')->where('id', $assigned->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->system_key)->toBeNull()
        ->and($row->name)->toBe('Assegnato');
});

// ---------------------------------------------------------------------------
// AC-019 — seeder idempotency
// ---------------------------------------------------------------------------

it('AC-019: re-running the taxonomy seeder stays idempotent on the "Assegnato" row', function () {
    test()->seed(QualificaTaskTaxonomySeeder::class);
    test()->seed(QualificaTaskTaxonomySeeder::class);

    expect(TaskStatus::query()->where('name', 'Assegnato')->count())->toBe(1)
        ->and(TaskStatus::query()->where('system_key', TaskStatusSystemKey::Assigned->value)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-020/AC-021 — SystemStatusGuard on the newly protected row
// ---------------------------------------------------------------------------

it('AC-020: DELETE on the "assigned" row is 422 from SystemStatusGuard, and the row survives', function () {
    Sanctum::actingAs(assignedStatusActorWith(['delete']));
    $assigned = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Assigned->value)->firstOrFail();

    $this->deleteJson("/api/task-statuses/{$assigned->id}")->assertStatus(422);

    $this->assertDatabaseHas('task_statuses', ['id' => $assigned->id]);
});

it('AC-021: PATCH on the "assigned" row rejects a group change but accepts name/color', function () {
    Sanctum::actingAs(assignedStatusActorWith(['update']));
    $assigned = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Assigned->value)->firstOrFail();

    $this->patchJson("/api/task-statuses/{$assigned->id}", ['group' => TaskStatusGroup::Pending->value])
        ->assertStatus(422);

    $this->patchJson("/api/task-statuses/{$assigned->id}", ['name' => 'In carico', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'In carico')
        ->assertJsonPath('data.system_key', TaskStatusSystemKey::Assigned->value);
});

// ---------------------------------------------------------------------------
// D-4 — the initial-status derivation, at the resolver's own unit level
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-4): the creator no longer counts — a
// single assignee who is ONLY the creator (not the requester too) now
// resolves to the assigned row, the opposite of spec 0118's own rule.
it('D-4 (spec 0153): a single assignee who is ONLY the creator now resolves to the assigned row', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$creator->id], $creator->id, $requester->id);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('D-4: a single assignee who is the requester resolves to the open row', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$requester->id], $creator->id, $requester->id);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Open));
});

it('D-4: the creator and the requester together as two assignees resolve to the assigned row', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$creator->id, $requester->id], $creator->id, $requester->id);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('D-4: the creator plus an unrelated third assignee resolve to the assigned row', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();
    $other = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$creator->id, $other->id], $creator->id, $requester->id);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('D-4: a single assignee who is neither the creator nor the requester resolves to the assigned row', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();
    $other = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$other->id], $creator->id, $requester->id);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

// REQUIREMENT CHANGED (spec 0153, D-4): the creator no longer counts, so a
// null requester now means NOTHING can match the sole assignee — even when
// that assignee is the creator.
it('D-4 (spec 0153): a null requester means the sole creator-assignee now resolves to the assigned row', function () {
    $creator = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$creator->id], $creator->id, null);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('D-4: a null requester does not accidentally match a single unrelated assignee', function () {
    $creator = User::factory()->create();
    $other = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$other->id], $creator->id, null);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('D-4 (spec 0153): duplicate assignee ids collapse to one before the single-assignee check', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();

    $statusId = (new TaskInitialStatusResolver)->resolve([$requester->id, $requester->id], $creator->id, $requester->id);

    expect($statusId)->toBe(systemStatusIdFor(TaskStatusSystemKey::Open));
});

// ---------------------------------------------------------------------------
// AC-009..AC-015 — the derivation end-to-end, through POST/PATCH /api/tasks
// ---------------------------------------------------------------------------

if (! function_exists('grantTasksAbility')) {
    /**
     * Grants a single `tasks.*` ability. Deliberately NOT `taskActorWith`
     * (spec 0118 AC-068 tracks its ability list across 11 files — these
     * tests only ever need one or two abilities each, so pulling the shared
     * helper into a 12th file would just be a new spot for that trap).
     */
    function grantTasksAbility(User $user, string $ability): void
    {
        Permission::findOrCreate("tasks.{$ability}");
        $user->givePermissionTo("tasks.{$ability}");
    }
}

// REQUIREMENT CHANGED (spec 0153, D-4): the creator no longer counts — a
// single assignee who is only the creator (a different requester is set)
// now persists the assigned row.
it('AC-010 (spec 0153): POST with assignee_ids = [the creator] alone now persists the assigned row', function () {
    $creator = User::factory()->create();
    grantTasksAbility($creator, 'create');
    Sanctum::actingAs($creator);

    $response = $this->postJson('/api/tasks', [
        'title' => 'Attivita personale',
        'requester_id' => User::factory()->create()->id,
        'assignee_ids' => [$creator->id],
        'end_date' => '2026-12-31',
    ])->assertCreated();

    expect($response->json('data.task_status_id'))->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('AC-011: POST with requester_id = X and assignee_ids = [X] persists the open row', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();
    grantTasksAbility($creator, 'create');
    Sanctum::actingAs($creator);

    $response = $this->postJson('/api/tasks', [
        'title' => 'Delega al richiedente',
        'requester_id' => $requester->id,
        'assignee_ids' => [$requester->id],
        'end_date' => '2026-12-31',
    ])->assertCreated();

    expect($response->json('data.task_status_id'))->toBe(systemStatusIdFor(TaskStatusSystemKey::Open));
});

it('AC-012: POST with assignee_ids = [a third user] persists the assigned row', function () {
    $creator = User::factory()->create();
    grantTasksAbility($creator, 'create');
    Sanctum::actingAs($creator);

    $response = $this->postJson('/api/tasks', [
        'title' => 'Assegnata a un collega',
        'requester_id' => User::factory()->create()->id,
        'assignee_ids' => [User::factory()->create()->id],
        'end_date' => '2026-12-31',
    ])->assertCreated();

    expect($response->json('data.task_status_id'))->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('AC-013: two assignees (creator + requester) persist the assigned row: the rule is single-assignee only (D-4)', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();
    grantTasksAbility($creator, 'create');
    Sanctum::actingAs($creator);

    $response = $this->postJson('/api/tasks', [
        'title' => 'Due assegnatari interni',
        'requester_id' => $requester->id,
        'assignee_ids' => [$creator->id, $requester->id],
        'end_date' => '2026-12-31',
    ])->assertCreated();

    expect($response->json('data.task_status_id'))->toBe(systemStatusIdFor(TaskStatusSystemKey::Assigned));
});

it('AC-014: PATCH assignee_ids on a Task in "Da assegnare" does not move the status (D-6)', function () {
    $creator = User::factory()->create();
    grantTasksAbility($creator, 'view');
    grantTasksAbility($creator, 'update');
    $open = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Open->value)->firstOrFail();
    $task = Task::factory()->forCreator($creator)->inStatus($open)->create();
    $task->assignees()->attach($creator->id);
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['assignee_ids' => [User::factory()->create()->id]])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', $open->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
});

it('AC-015: PATCH assignee_ids on a Task in an advanced phase does not roll the status back', function () {
    $creator = User::factory()->create();
    grantTasksAbility($creator, 'view');
    grantTasksAbility($creator, 'update');
    $advanced = TaskStatus::query()->where('system_key', TaskStatusSystemKey::InProgress->value)->firstOrFail();
    $task = Task::factory()->forCreator($creator)->inStatus($advanced)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['assignee_ids' => [User::factory()->create()->id]])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', $advanced->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $advanced->id]);
});

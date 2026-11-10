<?php

use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System-row rules for `task-statuses` (spec 0101, AC-042/043/044/047)
|--------------------------------------------------------------------------
|
| AC-047 rectified 2026-11-10: the protected rows are no longer pinned
| head/tail, the reorder set is EVERY row.
|
| The three PROTECTED rows are created by the migrations, so every test reads
| them back rather than building them: `system_key` is UNIQUE and is not
| mass-assignable, so a second `open` row could not be created anyway.
|
| Since the 2026-09-04 rectification of D-5 `system_key` marks protection
| only; the PHASE is `group` (App\Enums\TaskStatusGroup), which is why the
| dataset below lists three keys and not six — `in_progress`/`pending`/
| `in_validation` were phases, and are now group values.
|
| The shared guard is App\Services\Statuses\SystemStatusGuard, whose
| MUTABLE_SYSTEM_FIELDS spec 0101 widened to name/color/icon/
| completion_percentage. AC-048 — that the three older status configurators
| stay green after that widening — is verified by running their existing
| suites unchanged, not by re-asserting them here.
*/

if (! function_exists('taskStatusSystemActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskStatusSystemActorWith(array $abilities): User
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

if (! function_exists('systemTaskStatus')) {
    function systemTaskStatus(TaskStatusSystemKey $key): TaskStatus
    {
        return TaskStatus::query()->where('system_key', $key->value)->firstOrFail();
    }
}

/**
 * @return array<string, array{0: TaskStatusSystemKey}>
 */
dataset('systemTaskStatusKeys', [
    'open' => [TaskStatusSystemKey::Open],
    'closed_positive' => [TaskStatusSystemKey::ClosedPositive],
    'closed_negative' => [TaskStatusSystemKey::ClosedNegative],
]);

// ---------------------------------------------------------------------------
// AC-042 — a system row is undeletable, even when unused
// ---------------------------------------------------------------------------

it('AC-042: DELETE of a system status is 422 and the row survives, even with no task using it', function (TaskStatusSystemKey $key) {
    Sanctum::actingAs(taskStatusSystemActorWith(['delete']));
    $status = systemTaskStatus($key);

    expect($status->tasks()->count())->toBe(0);

    $this->deleteJson("/api/task-statuses/{$status->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$status->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('task_statuses', ['id' => $status->id]);
})->with('systemTaskStatusKeys');

it('AC-042: the generic bulk-delete cannot remove a system status either', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['viewAny', 'delete']));
    $system = systemTaskStatus(TaskStatusSystemKey::Open);
    $custom = TaskStatus::factory()->create();

    $this->postJson('/api/tables/task-statuses/bulk-delete', ['ids' => [$system->id, $custom->id]]);

    $this->assertDatabaseHas('task_statuses', ['id' => $system->id]);
    $this->assertDatabaseMissing('task_statuses', ['id' => $custom->id]);
});

// ---------------------------------------------------------------------------
// AC-043 — the four mutable keys on a system row, and nothing else. `group`
// is NOT among them: the phase of a protected row is fixed by the migration.
// ---------------------------------------------------------------------------

it('AC-043: a system row accepts name, color, icon and completion_percentage together', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $closedNegative = systemTaskStatus(TaskStatusSystemKey::ClosedNegative);

    $this->patchJson("/api/task-statuses/{$closedNegative->id}", [
        'name' => 'Annullato', 'color' => 'teal', 'icon' => 'clock', 'completion_percentage' => 55,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Annullato')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.icon', 'clock')
        ->assertJsonPath('data.completion_percentage', 55)
        ->assertJsonPath('data.system_key', 'closed_negative');

    $this->assertDatabaseHas('task_statuses', [
        'id' => $closedNegative->id, 'name' => 'Annullato', 'color' => 'teal',
        'icon' => 'clock', 'completion_percentage' => 55, 'system_key' => 'closed_negative',
    ]);
});

it('AC-043: a system row rejects is_active, and nothing is persisted', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $open = systemTaskStatus(TaskStatusSystemKey::Open);

    // Status only, not the message: that sentence is factually wrong since
    // the guard admits four fields, and is kept solely because nine existing
    // assertions elsewhere pin it and AC-048 needs that suite green. Pinning
    // it again here would make the eventual correction more expensive for no
    // gain in coverage.
    $this->patchJson("/api/task-statuses/{$open->id}", ['is_active' => false])->assertStatus(422);

    $this->assertDatabaseHas('task_statuses', ['id' => $open->id, 'is_active' => true]);
});

it('AC-043: a system row rejects description, and nothing is persisted', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $open = systemTaskStatus(TaskStatusSystemKey::Open);

    $this->patchJson("/api/task-statuses/{$open->id}", ['description' => 'Testo nuovo'])
        ->assertStatus(422);

    $this->assertDatabaseHas('task_statuses', ['id' => $open->id, 'description' => null]);
});

it('AC-043: a system row rejects is_active even when sent alongside allowed keys', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $open = systemTaskStatus(TaskStatusSystemKey::Open);
    $originalName = $open->name;

    // The guard checks the submitted KEY set: one forbidden key poisons the
    // whole payload, it does not get silently dropped.
    $this->patchJson("/api/task-statuses/{$open->id}", ['name' => 'Nuovo nome', 'is_active' => false])
        ->assertStatus(422);

    $this->assertDatabaseHas('task_statuses', ['id' => $open->id, 'name' => $originalName, 'is_active' => true]);
});

it('AC-043: a system row rejects a key even when the value is the one already persisted', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $open = systemTaskStatus(TaskStatusSystemKey::Open);

    // `is_active` is already true and `description` already null: a guard
    // comparing VALUES would see no change and let both through. It compares
    // submitted KEYS, so both are 422.
    $this->patchJson("/api/task-statuses/{$open->id}", ['is_active' => true])->assertStatus(422);
    $this->patchJson("/api/task-statuses/{$open->id}", ['description' => null])->assertStatus(422);

    $this->assertDatabaseHas('task_statuses', ['id' => $open->id, 'is_active' => true, 'description' => null]);
});

it('AC-043: a system row rejects group, so its phase cannot be moved', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $open = systemTaskStatus(TaskStatusSystemKey::Open);

    // `group` passes the FormRequest (it is a legal value) and is stopped by
    // SystemStatusGuard, one layer further in: the phase of a protected row
    // is fixed by the migration, not admin-configurable.
    $this->patchJson("/api/task-statuses/{$open->id}", ['group' => 'closed_negative'])
        ->assertStatus(422);

    $this->assertDatabaseHas('task_statuses', ['id' => $open->id, 'group' => 'open']);
});

it('AC-043: a CUSTOM row still accepts is_active and description', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $custom = TaskStatus::factory()->create();

    $this->patchJson("/api/task-statuses/{$custom->id}", ['is_active' => false, 'description' => 'Testo'])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.description', 'Testo');
});

// ---------------------------------------------------------------------------
// AC-044 — closed_negative's percentage is configurable like any other
// ---------------------------------------------------------------------------

it('AC-044: closed_negative starts at 0 and can be moved, and the tasks in it follow', function () {
    $taskActor = User::factory()->create();
    Permission::findOrCreate('tasks.view');
    Permission::findOrCreate('tasks.viewAll');
    $taskActor->givePermissionTo(['tasks.view', 'tasks.viewAll']);

    $closedNegative = systemTaskStatus(TaskStatusSystemKey::ClosedNegative);
    expect($closedNegative->completion_percentage)->toBe(0);

    $task = Task::factory()->inStatus($closedNegative)->create();

    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $this->patchJson("/api/task-statuses/{$closedNegative->id}", ['completion_percentage' => 30])
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 30);

    Sanctum::actingAs($taskActor);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('data.completion_percentage', 30);
});

it('AC-044: closed_negative stays a CLOSING status after its percentage moves', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $closedNegative = systemTaskStatus(TaskStatusSystemKey::ClosedNegative);

    $this->patchJson("/api/task-statuses/{$closedNegative->id}", ['completion_percentage' => 100])->assertOk();

    // Closing comes from the PHASE (`group`), never from the percentage (D-5).
    expect($closedNegative->fresh()->isClosing())->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-047 (rectified 2026-11-10) — reorder moves EVERY row, protected included
// ---------------------------------------------------------------------------

it('AC-047: a valid permutation of every row moves the protected rows too', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $custom = TaskStatus::factory()->create(['name' => 'Ordinaria']);
    $open = systemTaskStatus(TaskStatusSystemKey::Open);
    $closedNegative = systemTaskStatus(TaskStatusSystemKey::ClosedNegative);

    // The opening row goes LAST and the negative closing row FIRST: exactly
    // the moves the old head/tail pinning forbade.
    $middle = TaskStatus::query()->whereNotIn('id', [$open->id, $closedNegative->id])
        ->orderBy('sort_order')->pluck('id')->all();
    $orderedIds = [$closedNegative->id, ...$middle, $open->id];

    $response = $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => $orderedIds])->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe($orderedIds)
        ->and(TaskStatus::query()->orderBy('sort_order')->pluck('id')->all())->toBe($orderedIds)
        ->and($middle)->toContain($custom->id);

    // Moving a protected row changes its position only, never its protection
    // or its phase.
    expect($open->fresh()->system_key)->toBe(TaskStatusSystemKey::Open)
        ->and($closedNegative->fresh()->isClosing())->toBeTrue();
});

it('AC-047: 422 when ordered_ids omits a protected status, no sort_order changes', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    TaskStatus::factory()->create();
    $open = systemTaskStatus(TaskStatusSystemKey::Open);
    $before = TaskStatus::query()->orderBy('sort_order')->pluck('sort_order', 'id')->all();

    $withoutOpen = TaskStatus::query()->where('id', '!=', $open->id)->pluck('id')->all();

    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => $withoutOpen])->assertStatus(422);

    expect(TaskStatus::query()->orderBy('sort_order')->pluck('sort_order', 'id')->all())->toBe($before);
});

it('AC-047: 422 when ordered_ids carries only the custom statuses (the pre-rectification set)', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $first = TaskStatus::factory()->create();
    $second = TaskStatus::factory()->create();
    $firstOrder = $first->sort_order;

    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => [$second->id, $first->id]])->assertStatus(422);

    $this->assertDatabaseHas('task_statuses', ['id' => $first->id, 'sort_order' => $firstOrder]);
});

it('AC-047: 422 when ordered_ids repeats an id', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $custom = TaskStatus::factory()->create();

    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => [$custom->id, $custom->id]])
        ->assertStatus(422);
});

it('AC-047: 422 when ordered_ids contains an id that does not exist', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $custom = TaskStatus::factory()->create();

    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => [$custom->id, 999999]])
        ->assertStatus(422);
});

it('AC-047: 403 without task-statuses.update, and the order is unchanged', function () {
    Sanctum::actingAs(taskStatusSystemActorWith([]));
    $custom = TaskStatus::factory()->create();
    $order = $custom->sort_order;

    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => [$custom->id]])->assertForbidden();

    $this->assertDatabaseHas('task_statuses', ['id' => $custom->id, 'sort_order' => $order]);
});

<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskAssigned;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task list — q-net alignment (spec 0156)
|--------------------------------------------------------------------------
|
| AC-001 (filters/search), AC-002 (new columns), AC-003 (aggregates),
| AC-005 (row actions), AC-007 (bulk), AC-009 (inline cell edit). AC-004/
| AC-006/AC-008/AC-010 are frontend-only (D-4/D-5/D-7/guides), out of this
| backend suite.
*/

if (! function_exists('taskActorWith')) {
    /**
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom (see TaskTableTest.php).
     *
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withViewAll) {
            $user->givePermissionTo('tasks.viewAll');
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — new advanced filters + exact-id search
// ---------------------------------------------------------------------------

it('AC-001: the registry and work_order advanced filters restrict the rows', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $workOrder = WorkOrder::factory()->create();

    $matching = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'title' => 'Nella commessa']);
    Task::factory()->forCreator($actor)->create(['title' => 'Fuori dalla commessa']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['assignment' => ['visible'], 'work_order' => [$workOrder->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('AC-001: due=this_month restricts the rows to the current calendar month', function () {
    Carbon\Carbon::setTestNow('2026-09-15 10:00:00');

    $actor = taskActorWith(['viewAny', 'view']);
    $inMonth = Task::factory()->forCreator($actor)->create(['title' => 'Nel mese', 'end_date' => '2026-09-28']);
    Task::factory()->forCreator($actor)->create(['title' => 'Fuori dal mese', 'end_date' => '2026-10-05']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['assignment' => ['visible'], 'due' => 'this_month'],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$inMonth->id]);

    Carbon\Carbon::setTestNow();
});

it('AC-001: the quick search also matches an exact numeric task id', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $target = Task::factory()->forCreator($actor)->create(['title' => 'Nessuna corrispondenza testuale']);
    $decoy = Task::factory()->forCreator($actor)->create(['title' => (string) $target->id.' nel titolo']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => (string) $target->id,
        'advancedFilters' => ['assignment' => ['visible']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id')->all();

    expect($ids)->toContain($target->id)
        ->and($ids)->toContain($decoy->id); // title LIKE still matches too (OR-combined).
});

// ---------------------------------------------------------------------------
// AC-002 — new columns
// ---------------------------------------------------------------------------

it('AC-002: rows expose the new columns and actual_minutes sorts server-side', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $parent = Task::factory()->forCreator($actor)->create(['title' => 'Genitore']);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create(['title' => 'Figlio']);
    Sanctum::actingAs($actor);

    TimeEntry::factory()->forUser($actor)->create(['task_id' => $child->id, 'minutes' => 45]);

    $response = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['assignment' => ['visible']],
        'sortModel' => [['colId' => 'actual_minutes', 'sort' => 'desc']],
    ])->assertOk();

    $items = collect($response->json('items'))->keyBy('id');

    expect($items[$child->id]['actual_minutes'])->toBe(45)
        ->and($items[$child->id]['parent_title'])->toBe('Genitore')
        ->and($items[$parent->id]['actual_minutes'])->toBe(0)
        ->and($items[$parent->id]['parent_title'])->toBeNull()
        ->and($items[$parent->id]['is_recurring'])->toBeFalse()
        ->and($items[$parent->id])->toHaveKey('updated_at')
        ->and($items[$parent->id])->toHaveKey('notes_count')
        ->and($items[$parent->id]['task_status'])->toHaveKey('group');

    // Sorted desc by actual_minutes: the child (45) comes before the parent (0).
    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$child->id, $parent->id]);
});

// ---------------------------------------------------------------------------
// AC-003 — meta.aggregates.estimated_minutes_total
// ---------------------------------------------------------------------------

it('AC-003: meta.aggregates.estimated_minutes_total sums the FILTERED set, not the page', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Task::factory()->forCreator($actor)->create(['title' => 'Uno', 'estimated_minutes' => 30]);
    Task::factory()->forCreator($actor)->create(['title' => 'Due', 'estimated_minutes' => 45]);
    Task::factory()->forCreator($actor)->create(['title' => 'Tre', 'estimated_minutes' => 100]);
    Sanctum::actingAs($actor);

    // A page of 2 rows: the aggregate must still be the sum of all three (175), not just the page.
    $response = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 2, 'advancedFilters' => ['assignment' => ['visible']],
        'sortModel' => [['colId' => 'title', 'sort' => 'asc']],
    ])->assertOk();

    expect($response->json('meta.aggregates.estimated_minutes_total'))->toBe(175);

    // Filtered down to one row: the aggregate follows the filter.
    $filtered = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => ['visible']],
        'filterModel' => ['title' => ['filterType' => 'text', 'type' => 'equals', 'filter' => 'Due']],
    ])->assertOk();

    expect($filtered->json('meta.aggregates.estimated_minutes_total'))->toBe(45);
});

// ---------------------------------------------------------------------------
// AC-005 — row actions from actionPermissions
// ---------------------------------------------------------------------------

it('AC-005: the block row action appears only when actionPermissions allows it, and executes the detail endpoint', function () {
    $actor = taskActorWith(['viewAny', 'view', 'block']);
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($openStatus)->create();
    Sanctum::actingAs($actor);

    $row = collect(
        $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => ['visible']],
        ])->assertOk()->json('items')
    )->firstWhere('id', $task->id);

    expect($row['actions'])->toContain('block')->not->toContain('unblock');

    $this->postJson("/api/tasks/{$task->id}/block")->assertOk();

    $blockedRow = collect(
        $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => ['visible']],
        ])->assertOk()->json('items')
    )->firstWhere('id', $task->id);

    expect($blockedRow['actions'])->toContain('unblock')->not->toContain('block');
});

it('AC-005: the block row action is absent without tasks.block, and the endpoint 403s', function () {
    $actor = taskActorWith(['viewAny', 'view']); // no `block`
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($openStatus)->create();
    Sanctum::actingAs($actor);

    $row = collect(
        $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => ['visible']],
        ])->assertOk()->json('items')
    )->firstWhere('id', $task->id);

    expect($row['actions'])->not->toContain('block');

    $this->postJson("/api/tasks/{$task->id}/block")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-007 — POST /api/tasks/bulk
// ---------------------------------------------------------------------------

it('AC-007: bulk assign replaces the assignees on every compatible task', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update']);
    $one = Task::factory()->forCreator($actor)->create();
    $two = Task::factory()->forCreator($actor)->create();
    $newAssignee = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks/bulk', [
        'action' => 'assign', 'task_ids' => [$one->id, $two->id], 'assignee_ids' => [$newAssignee->id],
    ])->assertOk();

    expect($response->json('data.affected'))->toBe(2)
        ->and($one->fresh()->assignees->pluck('id')->all())->toBe([$newAssignee->id])
        ->and($two->fresh()->assignees->pluck('id')->all())->toBe([$newAssignee->id]);
});

it('AC-007: one incompatible task -> 422 with incompatible_tasks, nothing changes on the rest', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update']);
    $openTask = Task::factory()->forCreator($actor)->create();
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $frozenTask = Task::factory()->forCreator($actor)->inStatus($closedStatus)->create();
    $newAssignee = User::factory()->create();
    $originalAssigneeIds = $openTask->assignees->pluck('id')->all();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks/bulk', [
        'action' => 'assign', 'task_ids' => [$openTask->id, $frozenTask->id], 'assignee_ids' => [$newAssignee->id],
    ])->assertStatus(422);

    expect($response->json('incompatible_tasks.0.id'))->toBe($frozenTask->id)
        ->and($response->json('errors.task_ids'))->not->toBeEmpty()
        // "nulla cambia": the otherwise-compatible task's assignees are UNCHANGED.
        ->and($openTask->fresh()->assignees->pluck('id')->all())->toBe($originalAssigneeIds);
});

it('AC-007: an id not visible to the actor is reported as incompatible', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update'], withViewAll: false);
    $foreignTask = Task::factory()->create(); // no role of the actor's on this one
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks/bulk', [
        'action' => 'priority', 'task_ids' => [$foreignTask->id], 'task_priority_id' => TaskPriority::factory()->create()->id,
    ])->assertStatus(422);

    expect($response->json('incompatible_tasks.0.id'))->toBe($foreignTask->id);
});

it('AC-007: bulk delete applies the 0153 D-5 cascade', function () {
    $actor = taskActorWith(['viewAny', 'view', 'delete']);
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($openStatus)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks/bulk', ['action' => 'delete', 'task_ids' => [$task->id]])
        ->assertOk()
        ->assertJsonPath('data.affected', 1);

    expect(Task::query()->find($task->id))->toBeNull();
});

it('AC-007: 403 without the base resource permission of the chosen action', function () {
    $actor = taskActorWith(['viewAny', 'view']); // no `tasks.update`
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks/bulk', [
        'action' => 'priority', 'task_ids' => [$task->id], 'task_priority_id' => TaskPriority::factory()->create()->id,
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-009 — inline cell edit
// ---------------------------------------------------------------------------

it('AC-009: the title cell saves via PATCH /api/tables/tasks/rows/{id}', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update']);
    $task = Task::factory()->forCreator($actor)->create(['title' => 'Vecchio titolo']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/tasks/rows/{$task->id}", ['column' => 'title', 'value' => 'Nuovo titolo'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Nuovo titolo');

    expect($task->fresh()->title)->toBe('Nuovo titolo');
});

it('AC-009: a cell PATCH on a closed task is rejected for a non-super-admin (row not editable)', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update']);
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closedStatus)->create(['title' => 'Chiusa']);
    Sanctum::actingAs($actor);

    // The row's own `editable` flag (contract) is false once closed for a
    // non-super-admin: TableCellUpdateService::update() refuses at step 2
    // (authorizeUpdate), before it ever reaches TaskWriteLock's own 422.
    $this->patchJson("/api/tables/tasks/rows/{$task->id}", ['column' => 'title', 'value' => 'Tentativo'])
        ->assertForbidden();

    expect($task->fresh()->title)->toBe('Chiusa');
});

it('AC-009: a cell PATCH on a closed task IS allowed for a super-admin', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update']);
    Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closedStatus)->create(['title' => 'Chiusa']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/tasks/rows/{$task->id}", ['column' => 'title', 'value' => 'Modificata da super-admin'])
        ->assertOk();

    expect($task->fresh()->title)->toBe('Modificata da super-admin');
});

it('AC-009: the assignees cell syncs and sends the 0153 D-13 notification to the newcomer', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update']);
    $task = Task::factory()->forCreator($actor)->create();
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);
    Notification::fake();

    $this->patchJson("/api/tables/tasks/rows/{$task->id}", ['column' => 'assignees', 'value' => [$newcomer->id]])
        ->assertOk();

    expect($task->fresh()->assignees->pluck('id')->all())->toBe([$newcomer->id]);
    Notification::assertSentTo($newcomer, TaskAssigned::class);
});

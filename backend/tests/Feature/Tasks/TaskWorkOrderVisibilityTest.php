<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Tasks\TaskVisibilityScope;
use App\Tables\Tasks\TaskAdvancedFilterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Spec 0379 — the commessa tier of the Task visibility
|--------------------------------------------------------------------------
|
| Whoever sees a Commessa (WorkOrderVisibilityScope + `work-orders.view`)
| also sees its NON private Tasks, with no role on them. Read-only, in union
| with the other tiers.
*/

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $taskAbilities
 * @param  array<int, string>  $workOrderAbilities
 */
function workOrderTierActor(array $taskAbilities = ['viewAny', 'view'], array $workOrderAbilities = ['view']): User
{
    foreach (['viewAny', 'view', 'update', 'delete', 'viewAll', 'viewSite', 'manageAll'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }

    foreach (['viewAny', 'view', 'viewAll', 'viewSite'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    Permission::findOrCreate('notes.create');

    $user = User::factory()->create();
    $user->givePermissionTo([
        ...array_map(fn (string $ability) => "tasks.{$ability}", $taskAbilities),
        ...array_map(fn (string $ability) => "work-orders.{$ability}", $workOrderAbilities),
    ]);

    return $user;
}

/** A migrated-like Task of the commessa: creator and requester only, no assignee. */
function workOrderTierTask(WorkOrder $workOrder, string $title, bool $private = false): Task
{
    $creator = User::factory()->create();
    $factory = $private ? Task::factory()->private() : Task::factory();

    return $factory->forCreator($creator)->create([
        'title' => $title,
        'requester_id' => $creator->id,
        'work_order_id' => $workOrder->id,
    ]);
}

/**
 * @return array<int, string>
 */
function workOrderTierGridTitles(): array
{
    $items = test()->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']]])
        ->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

/**
 * @return array<int, string>
 */
function workOrderTierBoardTitles(WorkOrder $workOrder): array
{
    $tasks = test()->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk()->json('data.tasks');

    return collect($tasks)->pluck('title')->sort()->values()->all();
}

it('AC-001 shows a supervisor the tasks of the commessa on the board, in the grid and on show', function () {
    $supervisor = workOrderTierActor();
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);
    $task = workOrderTierTask($workOrder, 'Primo contatto');
    workOrderTierTask(WorkOrder::factory()->create(), 'Altra commessa');
    Sanctum::actingAs($supervisor);

    $this->getJson("/api/tasks/{$task->id}")->assertOk();

    expect(workOrderTierBoardTitles($workOrder))->toBe(['Primo contatto'])
        ->and(workOrderTierGridTitles())->toBe(['Primo contatto']);
});

it('AC-002 shows the tasks to a participant and to whoever sees the commessa by site', function () {
    $participant = workOrderTierActor();
    $workOrder = WorkOrder::factory()->create();
    $workOrder->participants()->attach($participant->id, ['position' => 1]);
    workOrderTierTask($workOrder, 'Del partecipante');
    Sanctum::actingAs($participant);

    expect(workOrderTierGridTitles())->toBe(['Del partecipante']);

    $site = OperationalSite::factory()->create();
    $bySite = workOrderTierActor(workOrderAbilities: ['view', 'viewSite']);
    EmploymentProfile::factory()->physicalSite($site)->create(['user_id' => $bySite->id]);
    $siteWorkOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create(['operational_site_id' => $site->id])->id]);
    workOrderTierTask($siteWorkOrder, 'Della sede');
    Sanctum::actingAs($bySite);

    expect(workOrderTierGridTitles())->toBe(['Della sede']);
});

it('AC-003 keeps a private task of the commessa hidden from non members', function () {
    $supervisor = workOrderTierActor();
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);
    $private = workOrderTierTask($workOrder, 'Privato', private: true);
    Sanctum::actingAs($supervisor);

    $this->getJson("/api/tasks/{$private->id}")->assertForbidden();

    expect(workOrderTierBoardTitles($workOrder))->toBe([]);
});

it('AC-004 grants nothing without work-orders.view nor to whoever does not see the commessa', function () {
    $withoutPermission = workOrderTierActor(workOrderAbilities: []);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($withoutPermission->id);
    $task = workOrderTierTask($workOrder, 'Primo contatto');
    Sanctum::actingAs($withoutPermission);

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
    expect(workOrderTierGridTitles())->toBe([]);

    Sanctum::actingAs(workOrderTierActor());

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
    expect(workOrderTierGridTitles())->toBe([]);
});

it('AC-005 is read-only: no update nor delete without a record role or manageAll', function () {
    $supervisor = workOrderTierActor(['viewAny', 'view', 'update', 'delete']);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);
    $task = workOrderTierTask($workOrder, 'Primo contatto');
    Sanctum::actingAs($supervisor);

    expect($this->patchJson("/api/tasks/{$task->id}", ['title' => 'Cambiato'])->status())->toBeIn([403, 422]);
    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

    expect($supervisor->can('view', $task))->toBeTrue()
        ->and($supervisor->can('update', $task))->toBeFalse()
        ->and($task->fresh()->title)->toBe('Primo contatto');
});

it('AC-006 lets work-orders.viewAll see the tasks of every commessa, not those without one', function () {
    $actor = workOrderTierActor(workOrderAbilities: ['view', 'viewAll']);
    workOrderTierTask(WorkOrder::factory()->create(), 'Commessa A');
    workOrderTierTask(WorkOrder::factory()->create(), 'Commessa B');
    workOrderTierTask(WorkOrder::factory()->create(), 'Privato', private: true);
    Task::factory()->create(['title' => 'Senza commessa']);
    Sanctum::actingAs($actor);

    expect(workOrderTierGridTitles())->toBe(['Commessa A', 'Commessa B']);
});

it('AC-007 the in-memory record shape agrees with the query shape', function () {
    $supervisor = workOrderTierActor(['view']);
    $mine = WorkOrder::factory()->create();
    $mine->supervisors()->attach($supervisor->id);
    workOrderTierTask($mine, 'Mia commessa');
    workOrderTierTask($mine, 'Privato', private: true);
    workOrderTierTask(WorkOrder::factory()->create(), 'Altra commessa');
    Task::factory()->create(['title' => 'Senza commessa']);

    $loaded = Task::query()->with(['assignees', 'watchers'])->get();
    $queried = TaskVisibilityScope::scopeToActor(Task::query(), $supervisor)->pluck('id')->all();

    foreach ($loaded as $task) {
        expect(TaskVisibilityScope::isVisibleTo($supervisor, $task))->toBe(in_array($task->id, $queried, true))
            ->and(TaskVisibilityScope::isVisibleTo($supervisor, Task::query()->findOrFail($task->id)))->toBe(in_array($task->id, $queried, true));
    }

    expect($loaded->filter(fn (Task $task) => TaskVisibilityScope::isVisibleTo($supervisor, $task))->pluck('title')->all())
        ->toBe(['Mia commessa']);
});

it('AC-008 makes the supervisor mentionable on the non private tasks of the commessa only', function () {
    $creator = workOrderTierActor(['view']);
    $creator->givePermissionTo('notes.create');
    $supervisor = workOrderTierActor(['view']);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);
    $open = Task::factory()->forCreator($creator)->create(['requester_id' => $creator->id, 'work_order_id' => $workOrder->id]);
    $private = Task::factory()->private()->forCreator($creator)->create(['requester_id' => $creator->id, 'work_order_id' => $workOrder->id]);
    Sanctum::actingAs($creator);

    $ids = fn (Task $task) => collect($this->getJson("/api/notes/mentionable-users?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()->json('items'))->pluck('id')->all();

    expect($ids($open))->toContain($supervisor->id)
        ->and($ids($private))->not->toContain($supervisor->id);
});

it('AC-009 offers the "visible" assignment option to whoever holds work-orders.view', function () {
    $excluded = fn (User $actor) => collect(TaskAdvancedFilterCatalog::advancedFilters($actor))
        ->firstWhere('name', 'assignment')['excludedValues'];

    expect($excluded(workOrderTierActor()))->not->toContain('visible')
        ->and($excluded(workOrderTierActor(workOrderAbilities: [])))->toContain('visible');
});

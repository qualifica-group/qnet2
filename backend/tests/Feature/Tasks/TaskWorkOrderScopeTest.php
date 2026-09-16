<?php

use App\Models\ExportRun;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `WorkOrderScopedTableDefinition` (spec 0133, D-1): scopes the `tasks`
 * domain's rows/values/columns/export endpoints to a single Work Order via the
 * `workOrderId`/`work_order_id` request parameter — the Commessa detail's
 * "Task" tab. AC-001..AC-008. Ricalca WorkOrderQuoteScopeTest (spec 0095).
 */
uses(RefreshDatabase::class);

if (! function_exists('taskActorWith')) {
    /**
     * Duplicated (guarded) from TaskTableTest, following the repo idiom for
     * shared Pest helpers.
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

/**
 * @param  array<string, mixed>  $extra
 * @return array<int, string>
 */
function scopedTaskTitles(array $extra): array
{
    $items = test()->postJson('/api/tables/tasks/rows', array_merge(['startRow' => 0, 'endRow' => 50], $extra))
        ->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

it('AC-001: rows scoped to work order A return exactly A\'s tasks, and pagination.total matches', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $workOrderA = WorkOrder::factory()->create();
    $workOrderB = WorkOrder::factory()->create();
    Task::factory()->create(['title' => 'A1', 'work_order_id' => $workOrderA->id]);
    Task::factory()->create(['title' => 'A2', 'work_order_id' => $workOrderA->id]);
    Task::factory()->create(['title' => 'B1', 'work_order_id' => $workOrderB->id]);
    Task::factory()->create(['title' => 'Libero']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'workOrderId' => $workOrderA->id,
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('title')->sort()->values()->all())->toBe(['A1', 'A2'])
        ->and($response->json('pagination.total'))->toBe(2);
});

it('AC-002: omitting workOrderId (or null) returns every visible task, unchanged', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $workOrder = WorkOrder::factory()->create();
    Task::factory()->create(['title' => 'Collegato', 'work_order_id' => $workOrder->id]);
    Task::factory()->create(['title' => 'Libero']);
    Sanctum::actingAs($actor);

    expect(scopedTaskTitles([]))->toBe(['Collegato', 'Libero'])
        ->and(scopedTaskTitles(['workOrderId' => null]))->toBe(['Collegato', 'Libero']);
});

it('AC-003: the work order scope is ANDed with the visibility scope, never widening it', function () {
    $actor = taskActorWith(['viewAny', 'view'], withViewAll: false);
    $workOrder = WorkOrder::factory()->create();
    Task::factory()->forCreator($actor)->create(['title' => 'Mio', 'work_order_id' => $workOrder->id]);
    Task::factory()->create(['title' => 'Di altri', 'work_order_id' => $workOrder->id]);
    Task::factory()->forCreator($actor)->create(['title' => 'Mio fuori commessa']);
    Sanctum::actingAs($actor);

    expect(scopedTaskTitles(['workOrderId' => $workOrder->id]))->toBe(['Mio']);
});

it('AC-004: distinct values are computed only from the work order\'s tasks', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $workOrderA = WorkOrder::factory()->create();
    $inScope = TaskPriority::factory()->create(['name' => 'Alta']);
    $outOfScope = TaskPriority::factory()->create(['name' => 'Bassa']);
    Task::factory()->create(['task_priority_id' => $inScope->id, 'work_order_id' => $workOrderA->id]);
    Task::factory()->create(['task_priority_id' => $outOfScope->id]);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/tasks/values', [
        'columnId' => 'task_priority', 'limit' => 25, 'workOrderId' => $workOrderA->id,
    ])->assertOk()->json('data.values');

    expect($values)->toBe(['Alta']);
});

it('AC-005: a nonexistent or non-numeric workOrderId is rejected with 422 on rows, values and columns', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 25, 'workOrderId' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('workOrderId');
    $this->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 25, 'workOrderId' => 'x'])
        ->assertStatus(422)->assertJsonValidationErrors('workOrderId');
    $this->postJson('/api/tables/tasks/values', ['columnId' => 'task_priority', 'workOrderId' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('workOrderId');
    $this->getJson('/api/tables/tasks/columns?work_order_id=999999')
        ->assertStatus(422)->assertJsonValidationErrors('work_order_id');
});

it('AC-005: the columns response is identical with and without work_order_id', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $unscoped = $this->getJson('/api/tables/tasks/columns')->assertOk()->json('data');
    $scoped = $this->getJson('/api/tables/tasks/columns?work_order_id='.$workOrder->id)->assertOk()->json('data');

    expect($scoped)->toBe($unscoped);
});

it('AC-006: workOrderId is a no-op on a domain other than tasks', function () {
    $actor = User::factory()->create();
    Permission::findOrCreate('work-orders.viewAny');
    $actor->givePermissionTo('work-orders.viewAny');
    $workOrder = WorkOrder::factory()->create();
    WorkOrder::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $unscoped = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('pagination.total');
    $withWorkOrderId = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'workOrderId' => $workOrder->id,
    ])->assertOk()->json('pagination.total');

    expect($withWorkOrderId)->toBe($unscoped);
});

it('AC-007: a user without tasks.viewAny is denied even with workOrderId set', function () {
    $actor = taskActorWith([], withViewAll: false);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'workOrderId' => $workOrder->id,
    ])->assertForbidden();
});

it('AC-008: the export freezes workOrderId and contains only the work order\'s tasks', function () {
    Storage::fake('local');
    $actor = taskActorWith(['viewAny', 'view', 'export']);
    $workOrderA = WorkOrder::factory()->create();
    Task::factory()->create(['title' => 'Nella commessa', 'work_order_id' => $workOrderA->id]);
    Task::factory()->create(['title' => 'Fuori commessa']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/tasks', [
        'format' => 'csv',
        'columns' => [['colId' => 'title', 'header' => 'Title']],
        'workOrderId' => $workOrderA->id,
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();
    $csv = Storage::disk('local')->get($run->file_path);

    expect($run->state['workOrderId'])->toBe($workOrderA->id)
        ->and($run->row_count)->toBe(1)
        ->and($csv)->toContain('Nella commessa')->not->toContain('Fuori commessa');
});

<?php

use App\Models\BusinessFunction;
use App\Models\MigrationRun;
use App\Models\ProductCategory;
use App\Models\Task;
use App\Models\TaskTemplateItem;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/**
 * `work-order-workflows` source (spec 0322, D-11): AC-016. Fixtures mirror the real legacy shape.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyWorkOrderWorkflowsImport(array $records): MigrationRun
{
    // A fresh factory per run: stubs registered earlier would otherwise win over this run's records
    Http::swap(new HttpFactory);
    Http::fake([
        fakeMigrationsBaseUrl().'/work-order-workflows*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'work-order-workflows']);
    runMigrationJobFor($run);

    return $run->fresh();
}

/**
 * @return array<string, mixed>
 */
function isoWorkflowRecord(): array
{
    return [
        'id' => 1,
        'name' => 'ISO',
        'business_function_id' => 2,
        'product_category_id' => null,
        'statuses' => [
            ['id' => 1, 'name' => '0 _ Da Contattare Telefonicamente', 'progress_percentage' => 0, 'system_key' => null, 'group' => 'in_progress'],
            ['id' => 2, 'name' => '0.1 _ I° Contatto Email', 'progress_percentage' => 5, 'system_key' => null, 'group' => 'in_progress'],
        ],
        'warnings' => [],
    ];
}

beforeEach(function () {
    seedMigrationsConfig();
    BusinessFunction::factory()->create()->forceFill(['old_id' => 2])->save();
});

it('AC-016: creates one active workflow with criteria and ordered custom statuses carrying old_id, without activity log', function () {
    Activity::query()->delete();

    $run = runLegacyWorkOrderWorkflowsImport([isoWorkflowRecord()]);

    $workflow = WorkOrderWorkflow::query()->with(['criteria', 'statuses'])->firstOrFail();
    $customs = $workflow->statuses->whereNull('system_key')->sortBy('sort_order')->values();

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)
        ->and($workflow->name)->toBe('ISO')->and($workflow->is_active)->toBeTrue()
        ->and($workflow->criteria->map->only(['field', 'value_id'])->all())->toBe([
            ['field' => 'business_function_id', 'value_id' => BusinessFunction::query()->where('old_id', 2)->value('id')],
        ])
        ->and($customs->pluck('name')->all())->toBe(['0 _ Da Contattare Telefonicamente', '0.1 _ I° Contatto Email'])
        ->and($customs->pluck('old_id')->all())->toBe([1, 2])
        ->and($customs->pluck('progress_percentage')->all())->toBe([0, 5])
        ->and($customs->pluck('group')->map->value->unique()->all())->toBe(['in_progress'])
        ->and($workflow->statuses->pluck('system_key')->filter()->sort()->values()->all())->toBe(['closed', 'completed', 'open'])
        ->and(Activity::query()->whereIn('subject_type', [(new WorkOrderWorkflow)->getMorphClass(), (new WorkOrderWorkflowStatus)->getMorphClass()])->count())->toBe(0);
});

it('AC-016: a SOA-like record adds the category branch criterion', function () {
    $category = ProductCategory::factory()->create();
    $category->forceFill(['old_id' => 50])->save();

    $record = ['id' => 50, 'name' => 'SOA', 'business_function_id' => 2, 'product_category_id' => 50, 'statuses' => [
        ['id' => 20, 'name' => 'Controllo requisiti fattibilità', 'progress_percentage' => 6, 'system_key' => null, 'group' => 'in_progress'],
        ['id' => 21, 'name' => 'Invio contratto e modelli per richieste enti', 'progress_percentage' => null, 'system_key' => null, 'group' => 'in_progress'],
    ], 'warnings' => []];

    $run = runLegacyWorkOrderWorkflowsImport([$record]);
    $workflow = WorkOrderWorkflow::query()->with(['criteria', 'statuses'])->firstOrFail();

    expect($run->created_rows)->toBe(1)
        ->and($workflow->criteria->pluck('field')->sort()->values()->all())->toBe(['business_function_id', 'product_category_branch_id'])
        ->and($workflow->criteria->firstWhere('field', 'product_category_branch_id')->value_id)->toBe($category->id)
        ->and($workflow->statuses->whereNull('system_key')->sortBy('sort_order')->pluck('progress_percentage')->all())->toBe([6, null]);
});

it('AC-016: a re-run creates nothing more', function () {
    runLegacyWorkOrderWorkflowsImport([isoWorkflowRecord()]);
    $second = runLegacyWorkOrderWorkflowsImport([isoWorkflowRecord()]);

    expect($second->skipped_rows)->toBe(1)->and($second->created_rows)->toBe(0)
        ->and(WorkOrderWorkflow::query()->count())->toBe(1);
});

/**
 * @return array<string, mixed>
 */
function closingWorkflowRecord(): array
{
    $record = isoWorkflowRecord();
    $record['statuses'] = [
        ['id' => 1, 'name' => 'Start', 'progress_percentage' => 0, 'system_key' => 'open', 'group' => 'open'],
        ['id' => 2, 'name' => 'Working', 'progress_percentage' => 50, 'system_key' => null, 'group' => 'in_progress'],
        ['id' => 3, 'name' => 'Done keyed', 'progress_percentage' => 100, 'system_key' => 'completed', 'group' => 'completed'],
        ['id' => 4, 'name' => 'Done plain', 'progress_percentage' => 100, 'system_key' => null, 'group' => 'completed'],
        ['id' => 5, 'name' => 'Cancelled', 'progress_percentage' => null, 'system_key' => null, 'group' => 'closed'],
    ];

    return $record;
}

/**
 * @return Collection<int, WorkOrderWorkflowStatus>
 */
function importedSetRows(): Collection
{
    return WorkOrderWorkflowStatus::query()
        ->where('work_order_workflow_id', WorkOrderWorkflow::query()->value('id'))
        ->orderBy('sort_order')
        ->get();
}

it('AC-002/AC-003: a keyed legacy status becomes the system row, the other keys keep the default row, order follows D-3', function () {
    $run = runLegacyWorkOrderWorkflowsImport([closingWorkflowRecord()]);
    $rows = importedSetRows();

    expect($run->created_rows)->toBe(1)
        ->and($rows->pluck('name')->all())->toBe(['Start', 'Working', 'Done plain', 'Cancelled', 'Done keyed', 'Chiusa con esito negativo'])
        ->and($rows->pluck('sort_order')->all())->toBe([0, 10, 20, 30, 40, 50])
        ->and($rows->pluck('system_key')->all())->toBe(['open', null, null, null, 'completed', 'closed'])
        ->and($rows->pluck('group')->map->value->all())->toBe(['open', 'in_progress', 'completed', 'closed', 'completed', 'closed'])
        ->and($rows->pluck('old_id')->all())->toBe([1, 2, 4, 5, 3, null])
        ->and($rows->first()->progress_percentage)->toBe(0)
        ->and($rows->where('system_key', 'open')->count())->toBe(1);
});

/**
 * Builds a set the way the OLD import left it: every legacy status a custom in_progress row, default system rows in place.
 */
function oldStyleImportedSet(): WorkOrderWorkflow
{
    $record = isoWorkflowRecord();
    $record['statuses'] = array_map(fn (array $status) => [...$status, 'system_key' => null, 'group' => 'in_progress'], closingWorkflowRecord()['statuses']);
    runLegacyWorkOrderWorkflowsImport([$record]);

    return WorkOrderWorkflow::query()->sole();
}

it('AC-004: a re-run replaces default rows, repoints work orders, template items and tasks, and updates groups', function () {
    $workflow = oldStyleImportedSet();
    $defaultOpen = $workflow->statuses()->where('system_key', 'open')->sole();
    $defaultCompleted = $workflow->statuses()->where('system_key', 'completed')->sole();

    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['work_order_workflow_status_id' => $defaultCompleted->id])->save();
    $item = TaskTemplateItem::factory()->create();
    $item->forceFill(['work_order_workflow_status_id' => $defaultCompleted->id])->save();
    $task = Task::factory()->create();
    $task->forceFill(['work_order_workflow_status_id' => $defaultOpen->id])->save();

    $run = runLegacyWorkOrderWorkflowsImport([closingWorkflowRecord()]);
    $rows = importedSetRows();
    $done = $rows->firstWhere('old_id', 3);
    $start = $rows->firstWhere('old_id', 1);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)
        ->and(WorkOrderWorkflowStatus::query()->whereIn('id', [$defaultOpen->id, $defaultCompleted->id])->count())->toBe(0)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($done->id)
        ->and($item->fresh()->work_order_workflow_status_id)->toBe($done->id)
        ->and($task->fresh()->work_order_workflow_status_id)->toBe($start->id)
        ->and($rows->pluck('system_key')->all())->toBe(['open', null, null, null, 'completed', 'closed'])
        ->and($rows->firstWhere('old_id', 5)->group->value)->toBe('closed')
        ->and($rows->firstWhere('old_id', 4)->group->value)->toBe('completed')
        ->and($rows->where('old_id', '!=', null)->count())->toBe(5);
});

it('AC-005: a second identical re-run is skipped and writes nothing', function () {
    oldStyleImportedSet();
    runLegacyWorkOrderWorkflowsImport([closingWorkflowRecord()]);
    $before = WorkOrderWorkflowStatus::query()->orderBy('id')->get(['id', 'system_key', 'group', 'sort_order', 'updated_at'])->toArray();

    $second = runLegacyWorkOrderWorkflowsImport([closingWorkflowRecord()]);

    expect($second->skipped_rows)->toBe(1)->and($second->created_rows)->toBe(0)
        ->and(WorkOrderWorkflowStatus::query()->orderBy('id')->get(['id', 'system_key', 'group', 'sort_order', 'updated_at'])->toArray())->toBe($before);
});

it('AC-005: an adopted row the legacy no longer marks returns to custom with the group sent and the default key row comes back', function () {
    runLegacyWorkOrderWorkflowsImport([closingWorkflowRecord()]);

    $record = closingWorkflowRecord();
    $record['statuses'][2]['system_key'] = null;
    $run = runLegacyWorkOrderWorkflowsImport([$record]);
    $rows = importedSetRows();

    expect($run->created_rows)->toBe(1)
        ->and($rows->firstWhere('old_id', 3)->system_key)->toBeNull()
        ->and($rows->firstWhere('old_id', 3)->group->value)->toBe('completed')
        ->and($rows->where('system_key', 'completed')->count())->toBe(1)
        ->and($rows->firstWhere('system_key', 'completed')->old_id)->toBeNull()
        ->and($rows->pluck('sort_order')->all())->toBe([0, 10, 20, 30, 40, 50, 60]);
});

it('AC-005: a key moved to another legacy status demotes the previously adopted one', function () {
    runLegacyWorkOrderWorkflowsImport([closingWorkflowRecord()]);

    $record = closingWorkflowRecord();
    $record['statuses'][2]['system_key'] = null;
    $record['statuses'][3]['system_key'] = 'completed';
    runLegacyWorkOrderWorkflowsImport([$record]);
    $rows = importedSetRows();

    expect($rows->firstWhere('system_key', 'completed')->old_id)->toBe(4)
        ->and($rows->firstWhere('old_id', 3)->system_key)->toBeNull()
        ->and($rows->where('system_key', 'completed')->count())->toBe(1);
});

it('D-4: names and percentages already imported are not changed by a re-run', function () {
    oldStyleImportedSet();
    $record = closingWorkflowRecord();
    $record['statuses'][1]['name'] = 'Renamed in legacy';
    $record['statuses'][1]['progress_percentage'] = 99;

    runLegacyWorkOrderWorkflowsImport([$record]);

    expect(importedSetRows()->firstWhere('old_id', 2))->name->toBe('Working')->progress_percentage->toBe(50);
});

it('D-4: the global default set is never touched', function () {
    $status = WorkOrderWorkflowStatus::factory()->global()->create();
    $status->forceFill(['old_id' => 1])->save();

    $run = runLegacyWorkOrderWorkflowsImport([closingWorkflowRecord()]);

    expect($run->failed_rows)->toBe(0)->and($status->fresh()->group->value)->toBe('in_progress')->and($status->fresh()->system_key)->toBeNull();
});

it('AC-006: an invalid system_key, group or a duplicated key fails the row and writes nothing', function (string $field, mixed $value, ?string $key) {
    $record = closingWorkflowRecord();
    $record['statuses'][3][$field] = $value;

    if ($key !== null) {
        $record['statuses'][3]['system_key'] = $key;
        $record['statuses'][3]['group'] = $key;
    }

    $run = runLegacyWorkOrderWorkflowsImport([$record]);

    expect($run->failed_rows)->toBe(1)->and(WorkOrderWorkflow::query()->count())->toBe(0);
})->with([
    'unknown system_key' => ['system_key', 'archived', null],
    'unknown group' => ['group', 'paused', null],
    'key different from group' => ['system_key', 'closed', null],
    'duplicated key' => ['system_key', 'completed', 'completed'],
]);

it('AC-016: a workflow already configured with the same criteria is skipped', function () {
    runLegacyWorkOrderWorkflowsImport([isoWorkflowRecord()]);

    $other = isoWorkflowRecord();
    $other['id'] = 9;
    $other['statuses'] = [['id' => 99, 'name' => 'Other', 'progress_percentage' => 10, 'system_key' => null, 'group' => 'in_progress']];
    $run = runLegacyWorkOrderWorkflowsImport([$other]);

    expect($run->skipped_rows)->toBe(1)->and(WorkOrderWorkflow::query()->count())->toBe(1);
});

it('AC-016: an unresolved business function fails the row and writes nothing', function () {
    $record = isoWorkflowRecord();
    $record['business_function_id'] = 999;

    $run = runLegacyWorkOrderWorkflowsImport([$record]);

    expect($run->failed_rows)->toBe(1)->and($run->created_rows)->toBe(0)
        ->and(WorkOrderWorkflow::query()->count())->toBe(0);
});

it('AC-016: an unresolved product category fails the row and writes nothing', function () {
    $record = isoWorkflowRecord();
    $record['product_category_id'] = 777;

    $run = runLegacyWorkOrderWorkflowsImport([$record]);

    expect($run->failed_rows)->toBe(1)->and(WorkOrderWorkflow::query()->count())->toBe(0);
});

it('AC-016: a percentage of 101 fails the row and writes nothing', function () {
    $record = isoWorkflowRecord();
    $record['statuses'][1]['progress_percentage'] = 101;

    $run = runLegacyWorkOrderWorkflowsImport([$record]);

    expect($run->failed_rows)->toBe(1)->and(WorkOrderWorkflow::query()->count())->toBe(0);
});

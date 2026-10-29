<?php

use App\Migrations\MigrationOrder;
use App\Models\MigrationRun;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Task template import links each action to its working status (spec 0323, D-9). AC-017.

function importTemplateWithStatus(mixed $statusOldId, string $rootTitle = 'Linked'): MigrationRun
{
    seedMigrationsConfig();
    // A second import in the same test needs its own payload: Http::fake() keeps the first matching stub.
    Http::swap(new Factory);
    Http::fake([
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [[
                'id' => 7,
                'name' => 'Linked model',
                'is_active' => true,
                'warnings' => [],
                'stages' => [[
                    'id' => 70,
                    'name' => 'Stage',
                    'items' => [
                        ['id' => 700, 'parent_id' => null, 'title' => $rootTitle, 'description' => null, 'estimated_minutes' => null, 'work_order_workflow_status_old_id' => $statusOldId],
                        ['id' => 701, 'parent_id' => 700, 'title' => 'Unlinked child', 'description' => null, 'estimated_minutes' => null, 'work_order_workflow_status_old_id' => null],
                    ],
                ]],
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'task-templates']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-017: links the action to the status resolved by old_id', function () {
    $status = WorkOrderWorkflowStatus::factory()->create(['old_id' => 305]);

    importTemplateWithStatus(305);

    $items = TaskTemplate::query()->where('old_id', 7)->firstOrFail()->items()->orderBy('sort_order')->get();
    expect($items[0]->work_order_workflow_status_id)->toBe($status->id)
        ->and($items[1]->work_order_workflow_status_id)->toBeNull();
});

it('AC-017: an old_id not migrated leaves the link empty and reports a warning', function () {
    $run = importTemplateWithStatus(999);

    $item = TaskTemplate::query()->where('old_id', 7)->firstOrFail()->items()->orderBy('sort_order')->firstOrFail();
    expect($item->work_order_workflow_status_id)->toBeNull()
        ->and(collect($run->report)->where('level', 'warning')->pluck('message')->implode(' '))->toContain('old_id 999');
});

it('AC-017: task-templates now imports after work-order-workflows', function () {
    expect(MigrationOrder::phaseOf('task-templates'))->toBeGreaterThan(MigrationOrder::phaseOf('work-order-workflows'));
});

// Spec 0323 D-11: a model imported before the link existed is backfilled on a re-run.

function templateRootItem(): TaskTemplateItem
{
    return TaskTemplate::query()->where('old_id', 7)->firstOrFail()->items()->orderBy('sort_order')->firstOrFail();
}

it('D-11: a re-run links the empty actions of a model already imported', function () {
    $status = WorkOrderWorkflowStatus::factory()->create(['old_id' => 305]);
    importTemplateWithStatus(null);

    $run = importTemplateWithStatus(305);

    expect(templateRootItem()->work_order_workflow_status_id)->toBe($status->id)
        ->and(TaskTemplate::query()->where('old_id', 7)->count())->toBe(1)
        ->and(TaskTemplate::query()->where('old_id', 7)->firstOrFail()->items()->count())->toBe(2)
        ->and($run->created_rows)->toBe(1);
});

it('D-11: a link already set in qnet-2 is never overwritten', function () {
    $manual = WorkOrderWorkflowStatus::factory()->create();
    WorkOrderWorkflowStatus::factory()->create(['old_id' => 305]);
    importTemplateWithStatus(null);
    templateRootItem()->update(['work_order_workflow_status_id' => $manual->id]);

    $run = importTemplateWithStatus(305);

    expect(templateRootItem()->work_order_workflow_status_id)->toBe($manual->id)
        ->and($run->skipped_rows)->toBe(1);
});

it('D-11: a model whose actions changed in qnet-2 is left untouched with a warning', function () {
    WorkOrderWorkflowStatus::factory()->create(['old_id' => 305]);
    importTemplateWithStatus(null);

    $run = importTemplateWithStatus(305, 'Renamed in the legacy');

    expect(templateRootItem()->work_order_workflow_status_id)->toBeNull()
        ->and($run->skipped_rows)->toBe(1)
        ->and(collect($run->report)->where('level', 'warning')->pluck('message')->implode(' '))->toContain('not backfilled');
});

<?php

use App\Models\MigrationRun;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/**
 * `work-order-task-templates` source (spec 0326): AC-002..AC-004. Fixtures mirror the real legacy shape.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyWorkOrderTaskTemplatesImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/work-order-task-templates*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'work-order-task-templates']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function templateLinkWorkOrder(int $legacyId): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['old_id' => $legacyId])->save();

    return $workOrder;
}

function legacyTaskTemplate(int $legacyId): TaskTemplate
{
    $template = TaskTemplate::factory()->create();
    $template->forceFill(['old_id' => $legacyId])->save();
    TaskTemplateItem::factory()->create(['task_template_id' => $template->id]);

    return $template;
}

/**
 * @return array<string, mixed>
 */
function legacyTemplateLink(int $workOrderId, int $templateId): array
{
    return ['id' => $workOrderId, 'task_template_id' => $templateId, 'matched_tasks' => 11, 'template_actions' => 21, 'warnings' => []];
}

beforeEach(fn () => seedMigrationsConfig());

it('AC-002: links the template without activity log and without generating tasks', function () {
    $workOrder = templateLinkWorkOrder(6427);
    $template = legacyTaskTemplate(47);
    Activity::query()->delete();
    $tasksBefore = Task::query()->count();

    $run = runLegacyWorkOrderTaskTemplatesImport([legacyTemplateLink(6427, 47)]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)
        ->and($workOrder->fresh()->task_template_id)->toBe($template->id)
        ->and(Task::query()->count())->toBe($tasksBefore)
        ->and(Activity::query()->where('subject_type', (new WorkOrder)->getMorphClass())->count())->toBe(0);
});

it('AC-002: a re-run skips the work order already linked', function () {
    templateLinkWorkOrder(6427);
    legacyTaskTemplate(47);

    runLegacyWorkOrderTaskTemplatesImport([legacyTemplateLink(6427, 47)]);
    $second = runLegacyWorkOrderTaskTemplatesImport([legacyTemplateLink(6427, 47)]);

    expect($second->skipped_rows)->toBe(1)->and($second->created_rows)->toBe(0);
});

it('AC-003: a work order not migrated fails the row', function () {
    legacyTaskTemplate(47);

    $run = runLegacyWorkOrderTaskTemplatesImport([legacyTemplateLink(6427, 47)]);

    expect($run->failed_rows)->toBe(1)
        ->and(collect($run->report)->where('level', 'error')->pluck('message')->implode(' '))->toContain('Work order not migrated');
});

it('AC-003: a template not migrated fails the row and leaves the work order unchanged', function () {
    $workOrder = templateLinkWorkOrder(6427);

    $run = runLegacyWorkOrderTaskTemplatesImport([legacyTemplateLink(6427, 47)]);

    expect($run->failed_rows)->toBe(1)
        ->and($workOrder->fresh()->task_template_id)->toBeNull()
        ->and(collect($run->report)->where('level', 'error')->pluck('message')->implode(' '))->toContain('Task template not migrated');
});

it('AC-004: a work order on another template is left unchanged with a warning', function () {
    $workOrder = templateLinkWorkOrder(6427);
    legacyTaskTemplate(47);
    $chosen = legacyTaskTemplate(100);
    $workOrder->forceFill(['task_template_id' => $chosen->id])->saveQuietly();

    $run = runLegacyWorkOrderTaskTemplatesImport([legacyTemplateLink(6427, 47)]);

    expect($run->skipped_rows)->toBe(1)->and($run->failed_rows)->toBe(0)
        ->and($workOrder->fresh()->task_template_id)->toBe($chosen->id)
        ->and(collect($run->report)->where('level', 'warning')->count())->toBe(1);
});

it('AC-002: carries the legacy warnings in the report', function () {
    templateLinkWorkOrder(8146);
    legacyTaskTemplate(53);
    $record = [...legacyTemplateLink(8146, 53), 'warnings' => ['Modello ambiguo: 53 e 58 hanno gli stessi task in comune; scelto 53.']];

    $run = runLegacyWorkOrderTaskTemplatesImport([$record]);

    expect($run->created_rows)->toBe(1)
        ->and(collect($run->report)->where('level', 'warning')->pluck('message')->implode(' '))->toContain('Modello ambiguo');
});

<?php

use App\Models\BusinessFunction;
use App\Models\MigrationRun;
use App\Models\ProductCategory;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ['id' => 1, 'name' => '0 _ Da Contattare Telefonicamente', 'progress_percentage' => 0],
            ['id' => 2, 'name' => '0.1 _ I° Contatto Email', 'progress_percentage' => 5],
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
        ['id' => 20, 'name' => 'Controllo requisiti fattibilità', 'progress_percentage' => 6],
        ['id' => 21, 'name' => 'Invio contratto e modelli per richieste enti', 'progress_percentage' => null],
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

it('AC-016: a workflow already configured with the same criteria is skipped', function () {
    runLegacyWorkOrderWorkflowsImport([isoWorkflowRecord()]);

    $other = isoWorkflowRecord();
    $other['id'] = 9;
    $other['statuses'] = [['id' => 99, 'name' => 'Other', 'progress_percentage' => 10]];
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

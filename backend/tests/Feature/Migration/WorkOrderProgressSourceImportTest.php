<?php

use App\Models\MigrationRun;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/**
 * `work-order-progress` source (spec 0321, D-10): AC-013, AC-014, AC-015; revision 2026-10-28 (R-7): raw
 * increments 1:1, zeros included, sums above 100 allowed.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyWorkOrderProgressImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/work-order-progress*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'work-order-progress']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function progressWorkOrder(int $legacyId): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['old_id' => $legacyId])->save();

    return $workOrder;
}

beforeEach(fn () => seedMigrationsConfig());

it('AC-013: creates the raw rows as received, in order, without creator and without activity log', function () {
    $workOrder = progressWorkOrder(17);
    Activity::query()->delete();

    $run = runLegacyWorkOrderProgressImport([['id' => 17, 'entries' => [
        ['percentage' => 10, 'recorded_on' => '2024-07-03'],
        ['percentage' => 0, 'recorded_on' => '2024-08-01'],
        ['percentage' => 80, 'recorded_on' => '2025-02-28'],
        ['percentage' => 50, 'recorded_on' => '2025-01-10'],
    ]]]);

    $rows = $workOrder->progressEntries()->get();

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)
        ->and($rows->pluck('percentage')->all())->toBe([10, 0, 80, 50])
        ->and($rows->map(fn ($row) => $row->recorded_on->toDateString())->all())->toBe(['2024-07-03', '2024-08-01', '2025-02-28', '2025-01-10'])
        ->and((int) $rows->sum('percentage'))->toBe(140)
        ->and($rows->pluck('created_by')->filter()->all())->toBe([])
        ->and(Activity::query()->where('subject_type', (new WorkOrderProgressEntry)->getMorphClass())->count())->toBe(0);
});

it('AC-014: a work order that already has entries is skipped and left untouched', function () {
    $workOrder = progressWorkOrder(17);
    WorkOrderProgressEntry::factory()->create(['work_order_id' => $workOrder->id, 'percentage' => 40]);

    $run = runLegacyWorkOrderProgressImport([['id' => 17, 'entries' => [['percentage' => 80, 'recorded_on' => '2025-01-01']]]]);

    expect($run->skipped_rows)->toBe(1)->and($run->created_rows)->toBe(0)
        ->and($workOrder->progressEntries()->pluck('percentage')->all())->toBe([40]);
});

it('AC-014: a re-run changes nothing', function () {
    $workOrder = progressWorkOrder(17);
    $records = [['id' => 17, 'entries' => [['percentage' => 50, 'recorded_on' => '2025-01-01']]]];

    runLegacyWorkOrderProgressImport($records);
    $second = runLegacyWorkOrderProgressImport($records);

    expect($second->skipped_rows)->toBe(1)->and($workOrder->progressEntries()->count())->toBe(1);
});

it('AC-014: a work order not migrated fails the row', function () {
    $run = runLegacyWorkOrderProgressImport([['id' => 99, 'entries' => [['percentage' => 50, 'recorded_on' => '2025-01-01']]]]);

    expect($run->failed_rows)->toBe(1)->and($run->created_rows)->toBe(0)
        ->and(WorkOrderProgressEntry::query()->count())->toBe(0);
});

it('AC-015: an invalid entry list fails the row and writes nothing', function (array $entries) {
    $workOrder = progressWorkOrder(17);

    $run = runLegacyWorkOrderProgressImport([['id' => 17, 'entries' => $entries]]);

    expect($run->failed_rows)->toBe(1)->and($run->created_rows)->toBe(0)
        ->and($workOrder->progressEntries()->count())->toBe(0);
})->with([
    'above 100' => [[['percentage' => 10, 'recorded_on' => '2025-01-01'], ['percentage' => 101, 'recorded_on' => '2025-01-02']]],
    'negative' => [[['percentage' => -1, 'recorded_on' => '2025-01-01']]],
    'not an integer' => [[['percentage' => '10', 'recorded_on' => '2025-01-01']]],
    'invalid date' => [[['percentage' => 10, 'recorded_on' => '2025-02-30']]],
    'no entries' => [[]],
]);

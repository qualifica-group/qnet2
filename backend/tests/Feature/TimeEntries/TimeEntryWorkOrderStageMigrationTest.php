<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Spec 0163, AC-005: the `work_order_stage_id` backfill inside
 * `2026_09_25_120000_add_work_order_stage_id_to_time_entries_table` must
 * catch up every existing voce whose linked Task currently sits in a stage,
 * and leave untouched the ones whose Task has none or which carry no Task at
 * all.
 *
 * RefreshDatabase already runs every migration (including this one) once
 * before the test starts, so the migration file is `require`d a second time
 * here purely to call its `up()`/`down()` by hand — the same idiom
 * `ContactsNormalizedValueMigrationTest` already uses. Both calls run inside
 * the per-test transaction RefreshDatabase opened, so nothing needs
 * restoring afterwards.
 */
function workOrderStageBackfillMigration(): Migration
{
    return require database_path('migrations/2026_09_25_120000_add_work_order_stage_id_to_time_entries_table.php');
}

it('backfills work_order_stage_id from each linked task\'s current stage (AC-005)', function () {
    $migration = workOrderStageBackfillMigration();
    $migration->down();

    expect(Schema::hasColumn('time_entries', 'work_order_stage_id'))->toBeFalse();

    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $taskInStage = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);
    $taskWithoutStage = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => null]);

    $entryFromStagedTask = TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'task_id' => $taskInStage->id]);
    $entryFromUnstagedTask = TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'task_id' => $taskWithoutStage->id]);
    $entryWithoutTask = TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'task_id' => null]);

    $migration->up();

    expect(Schema::hasColumn('time_entries', 'work_order_stage_id'))->toBeTrue();

    expect(TimeEntry::query()->find($entryFromStagedTask->id)->work_order_stage_id)->toBe($stage->id)
        ->and(TimeEntry::query()->find($entryFromUnstagedTask->id)->work_order_stage_id)->toBeNull()
        ->and(TimeEntry::query()->find($entryWithoutTask->id)->work_order_stage_id)->toBeNull();
});

it('rolls back by dropping the work_order_stage_id column', function () {
    $migration = workOrderStageBackfillMigration();

    expect(Schema::hasColumn('time_entries', 'work_order_stage_id'))->toBeTrue();

    $migration->down();

    expect(Schema::hasColumn('time_entries', 'work_order_stage_id'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('time_entries', 'work_order_stage_id'))->toBeTrue();
});

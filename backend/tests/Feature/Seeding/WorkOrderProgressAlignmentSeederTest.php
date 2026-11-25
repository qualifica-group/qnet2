<?php

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Database\Seeders\WorkOrderProgressAlignmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// One-shot: the migrated commesse whose progress the legacy changed after the
// migration get the legacy entries back; one with entries typed in qnet-2 stays.

function alignedProgressOf(WorkOrder $workOrder): array
{
    return $workOrder->progressEntries()->get()
        ->map(fn (WorkOrderProgressEntry $entry): array => [$entry->percentage, $entry->recorded_on->toDateString()])
        ->all();
}

it('replaces the migrated entries with the current legacy ones', function () {
    $workOrder = WorkOrder::factory()->create(['old_id' => 12574]);
    foreach ([[5, '2025-02-28'], [14, '2025-03-31'], [9, '2025-04-30'], [9, '2025-05-31']] as [$percentage, $date]) {
        WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment($percentage, $date)->create();
    }

    $this->seed(WorkOrderProgressAlignmentSeeder::class);

    expect(alignedProgressOf($workOrder))->toBe([[100, '2025-10-31']])
        ->and($workOrder->progressEntries()->whereNotNull('created_by')->exists())->toBeFalse();
});

it('creates the entries of a commessa that has none and clears one the legacy emptied', function () {
    $missing = WorkOrder::factory()->create(['old_id' => 15903]);
    $emptied = WorkOrder::factory()->create(['old_id' => 20140]);
    WorkOrderProgressEntry::factory()->forWorkOrder($emptied)->increment(30, '2026-10-01')->create();

    $this->seed(WorkOrderProgressAlignmentSeeder::class);

    expect(alignedProgressOf($missing))->toBe([[100, '2026-05-14']])
        ->and(alignedProgressOf($emptied))->toBe([]);
});

it('leaves untouched a commessa with an entry typed in qnet-2', function () {
    $workOrder = WorkOrder::factory()->create(['old_id' => 12574]);
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment(5, '2025-02-28')->create();
    WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment(20, '2026-11-20')
        ->create(['created_by' => User::factory()->create()->id]);

    $this->seed(WorkOrderProgressAlignmentSeeder::class);

    expect(alignedProgressOf($workOrder))->toBe([[5, '2025-02-28'], [20, '2026-11-20']]);
});

it('changes nothing when run again', function () {
    $workOrder = WorkOrder::factory()->create(['old_id' => 12574]);

    $this->seed(WorkOrderProgressAlignmentSeeder::class);
    $ids = $workOrder->progressEntries()->pluck('id')->all();
    $this->seed(WorkOrderProgressAlignmentSeeder::class);

    expect($workOrder->progressEntries()->pluck('id')->all())->toBe($ids);
});

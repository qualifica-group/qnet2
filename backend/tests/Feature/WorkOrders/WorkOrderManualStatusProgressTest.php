<?php

use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function patchWorkOrderStatus($test, WorkOrder $workOrder, int $statusId)
{
    return $test->patchJson("/api/work-orders/{$workOrder->id}", ['work_order_workflow_status_id' => $statusId]);
}

it('AC-013: a manual change to a higher percentage adds the delta entry for today', function () {
    $actor = workOrderUserWith(['update']);
    Sanctum::actingAs($actor);
    $workOrder = WorkOrder::factory()->create();
    seedProgressEntry($workOrder, 20);
    $status = globalProgressStatus(60);

    patchWorkOrderStatus($this, $workOrder, $status->id)->assertOk();

    $entry = $workOrder->progressEntries()->where('created_by', $actor->id)->sole();
    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id)
        ->and($entry->percentage)->toBe(40)
        ->and($entry->recorded_on->toDateString())->toBe(now()->toDateString());
});

it('AC-014: a positive percentage not above the progress reached is refused with 422', function (int $percentage) {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = WorkOrder::factory()->create();
    $openId = $workOrder->work_order_workflow_status_id;
    seedProgressEntry($workOrder, 20);
    $status = globalProgressStatus($percentage);

    patchWorkOrderStatus($this, $workOrder, $status->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('work_order_workflow_status_id');

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($openId)
        ->and($workOrder->progressEntries()->count())->toBe(1);
})->with([20, 10]);

it('AC-014: the 422 message names both percentages', function () {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = WorkOrder::factory()->create();
    seedProgressEntry($workOrder, 20);

    patchWorkOrderStatus($this, $workOrder, globalProgressStatus(10)->id)
        ->assertJsonPath('errors.work_order_workflow_status_id.0', fn (string $m): bool => str_contains($m, '10%') && str_contains($m, '20%'));
});

it('AC-015: a null or zero percentage changes the status only', function (?int $percentage) {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = WorkOrder::factory()->create();
    seedProgressEntry($workOrder, 20);
    $status = globalProgressStatus($percentage);

    patchWorkOrderStatus($this, $workOrder, $status->id)->assertOk();

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id)
        ->and($workOrder->progressEntries()->count())->toBe(1);
})->with([null, 0]);

it('AC-015: resubmitting the current status, creating, and force-closing add no entries', function () {
    Sanctum::actingAs(workOrderUserWith(['update']));
    $workOrder = WorkOrder::factory()->create();
    $status = globalProgressStatus(60);
    $workOrder->forceFill(['work_order_workflow_status_id' => $status->id])->save();

    patchWorkOrderStatus($this, $workOrder, $status->id)->assertOk();
    expect($workOrder->progressEntries()->count())->toBe(0);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => true, 'force_close_reason' => 'Gone'])->assertOk();
    expect($workOrder->progressEntries()->count())->toBe(0);
});

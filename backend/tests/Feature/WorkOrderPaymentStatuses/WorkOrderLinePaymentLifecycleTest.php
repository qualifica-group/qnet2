<?php

use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Lifecycle of `work_order_line_payments` (spec 0201, D-11): AC-011.
 */
uses(RefreshDatabase::class);

it('AC-011: removing a line from the commessa deletes its payment record, the others stay', function () {
    foreach (['update', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.update', 'work-orders.viewAll']);

    $workOrder = WorkOrder::factory()->create();
    $kept = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $removed = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach([$kept->id, $removed->id]);
    WorkOrderLinePayment::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $kept->id]);
    WorkOrderLinePayment::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $removed->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$kept->id]])->assertOk();

    expect(WorkOrderLinePayment::where('quote_line_id', $removed->id)->exists())->toBeFalse()
        ->and(WorkOrderLinePayment::where('quote_line_id', $kept->id)->exists())->toBeTrue();
});

it('AC-011: resending the same lines keeps every payment record', function () {
    foreach (['update', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.update', 'work-orders.viewAll']);

    $workOrder = WorkOrder::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach($line->id);
    WorkOrderLinePayment::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $line->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$line->id]])->assertOk();

    expect(WorkOrderLinePayment::where('quote_line_id', $line->id)->exists())->toBeTrue();
});

it('the schema allows one payment record per quote line only', function () {
    $line = QuoteLine::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    WorkOrderLinePayment::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $line->id]);

    expect(fn () => WorkOrderLinePayment::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $line->id]))
        ->toThrow(QueryException::class);
});

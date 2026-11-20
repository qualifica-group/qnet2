<?php

use App\Migrations\Support\LegacyWorkOrderLinePaymentWriter;
use App\Migrations\Support\LegacyWorkOrderStageWriter;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Models\WorkOrderStage;
use Database\Seeders\WorkOrderPaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * Spec 0372, D-10/D-11: the stage and payment writers realign an already
 * imported commessa. Everything is written quietly, without activity log.
 */

function importedWorkOrder(array $legacyStages = []): WorkOrder
{
    return activity()->withoutLogs(function () use ($legacyStages): WorkOrder {
        $workOrder = WorkOrder::factory()->create();
        $warnings = [];
        (new LegacyWorkOrderStageWriter)->write($workOrder, $legacyStages, $warnings);

        return $workOrder;
    });
}

// AC-006
it('creates a new stage, closes one and keeps one the legacy no longer sends', function () {
    $closer = User::factory()->create(['old_id' => 77]);
    $workOrder = importedWorkOrder([['name' => 'Kickoff'], ['name' => 'Gone']]);
    $warnings = [];

    $written = (new LegacyWorkOrderStageWriter)->sync($workOrder, [
        ['name' => 'Kickoff', 'closed_at' => '2026-10-05 10:00:00', 'closed_by_user_id' => 77],
        ['name' => 'Delivery', 'closed_at' => null],
    ], $warnings);

    $stages = $workOrder->stages()->get()->keyBy('name');

    expect($written)->toBeTrue()
        ->and($stages)->toHaveCount(3)
        ->and($stages['Kickoff']->closed_at?->format('Y-m-d H:i:s'))->toBe('2026-10-05 10:00:00')
        ->and($stages['Kickoff']->closed_by_id)->toBe($closer->id)
        ->and($stages['Kickoff']->sort_order)->toBe(0)
        ->and($stages['Delivery']->sort_order)->toBe(1)
        ->and($stages['Gone']->closed_at)->toBeNull()
        ->and($warnings)->toBe(['Stage Gone is no longer in the legacy; kept.']);
});

it('reopens a closed stage when the legacy sends it open, and reorders', function () {
    $workOrder = importedWorkOrder([['name' => 'A'], ['name' => 'B', 'closed_at' => '2026-10-01 08:00:00']]);
    $warnings = [];

    $written = (new LegacyWorkOrderStageWriter)->sync($workOrder, [
        ['name' => 'B', 'closed_at' => null],
        ['name' => 'A', 'closed_at' => null],
    ], $warnings);

    $stages = $workOrder->stages()->get()->keyBy('name');

    expect($written)->toBeTrue()
        ->and($stages['B']->closed_at)->toBeNull()
        ->and($stages['B']->closed_by_id)->toBeNull()
        ->and($stages['B']->sort_order)->toBe(0)
        ->and($stages['A']->sort_order)->toBe(1)
        ->and($warnings)->toBe([]);
});

// AC-010 (stage part)
it('writes nothing and logs nothing when the stages already match', function () {
    $workOrder = importedWorkOrder([['name' => 'A'], ['name' => 'B', 'closed_at' => '2026-10-01 08:00:00']]);
    $stageUpdatedAt = $workOrder->stages()->pluck('updated_at', 'name');
    $activityBefore = DB::table('activity_log')->count();
    $warnings = [];

    $written = (new LegacyWorkOrderStageWriter)->sync($workOrder, [
        ['name' => 'A', 'closed_at' => null],
        ['name' => 'B', 'closed_at' => '2026-10-01 08:00:00'],
    ], $warnings);

    expect($written)->toBeFalse()
        ->and($warnings)->toBe([])
        ->and($workOrder->stages()->pluck('updated_at', 'name')->all())->toEqual($stageUpdatedAt->all())
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('never reduces the number of stages, even when the legacy sends none', function () {
    $workOrder = importedWorkOrder([['name' => 'A'], ['name' => 'B']]);
    $warnings = [];

    expect((new LegacyWorkOrderStageWriter)->sync($workOrder, [], $warnings))->toBeFalse()
        ->and(WorkOrderStage::query()->where('work_order_id', $workOrder->id)->count())->toBe(2)
        ->and($warnings)->toHaveCount(2);
});

it('sync matches stages by the truncated name like write', function () {
    $long = str_repeat('x', 200);
    $workOrder = importedWorkOrder([['name' => $long]]);
    $warnings = [];

    $written = (new LegacyWorkOrderStageWriter)->sync($workOrder, [['name' => $long]], $warnings);

    expect($written)->toBeFalse()
        ->and($workOrder->stages()->count())->toBe(1);
});

function workOrderWithPayments(): array
{
    $workOrder = WorkOrder::factory()->create();
    $lines = QuoteLine::factory()->count(2)->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach($lines->pluck('id'));

    return [$workOrder, $lines];
}

// AC-007
it('realigns the payment data on every line without duplicating rows', function () {
    $this->seed(WorkOrderPaymentStatusSeeder::class);
    $status = WorkOrderPaymentStatus::query()->whereNotNull('old_id')->firstOrFail();
    [$workOrder] = activity()->withoutLogs(fn () => workOrderWithPayments());
    $writer = new LegacyWorkOrderLinePaymentWriter;
    $warnings = [];

    activity()->withoutLogs(fn () => $writer->write($workOrder, ['stato_pagamento' => null, 'accordo_pagamento' => 'old', 'insoluti' => false], $warnings));
    expect(WorkOrderLinePayment::query()->count())->toBe(2);

    $written = $writer->sync($workOrder, ['stato_pagamento' => $status->old_id, 'accordo_pagamento' => ' new ', 'insoluti' => true], $warnings);

    $payments = WorkOrderLinePayment::query()->where('work_order_id', $workOrder->id)->get();
    expect($written)->toBeTrue()
        ->and($payments)->toHaveCount(2)
        ->and($payments->pluck('work_order_payment_status_id')->unique()->all())->toBe([$status->id])
        ->and($payments->pluck('payment_agreement')->unique()->all())->toBe(['new'])
        ->and($payments->every(fn (WorkOrderLinePayment $p): bool => $p->has_unpaid))->toBeTrue()
        ->and($warnings)->toBe([]);
});

it('creates the payment row of a line that has none yet', function () {
    [$workOrder, $lines] = activity()->withoutLogs(fn () => workOrderWithPayments());
    $warnings = [];

    $written = (new LegacyWorkOrderLinePaymentWriter)->sync($workOrder, ['accordo_pagamento' => 'x'], $warnings);

    expect($written)->toBeTrue()
        ->and(WorkOrderLinePayment::query()->where('work_order_id', $workOrder->id)->count())->toBe(2);
});

// AC-010 (payment part)
it('writes nothing when the payment data already matches', function () {
    [$workOrder] = activity()->withoutLogs(fn () => workOrderWithPayments());
    $writer = new LegacyWorkOrderLinePaymentWriter;
    $warnings = [];
    $record = ['stato_pagamento' => null, 'accordo_pagamento' => 'same', 'insoluti' => true];

    activity()->withoutLogs(fn () => $writer->write($workOrder, $record, $warnings));
    $activityBefore = DB::table('activity_log')->count();
    $updatedAt = WorkOrderLinePayment::query()->pluck('updated_at', 'id');

    expect($writer->sync($workOrder, $record, $warnings))->toBeFalse()
        ->and(WorkOrderLinePayment::query()->pluck('updated_at', 'id')->all())->toEqual($updatedAt->all())
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('leaves the status empty with a warning for an unknown legacy key', function () {
    [$workOrder] = activity()->withoutLogs(fn () => workOrderWithPayments());
    $warnings = [];

    (new LegacyWorkOrderLinePaymentWriter)->sync($workOrder, ['stato_pagamento' => 99999], $warnings);

    expect($warnings)->toBe(['Unresolved payment status (legacy key 99999); left empty.']);
});

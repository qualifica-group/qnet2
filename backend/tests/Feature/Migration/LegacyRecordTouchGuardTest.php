<?php

use App\Migrations\Support\LegacyRecordTouchGuard;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Spec 0372, D-6: "touched" = an activity_log entry exists. The import runs
 * without log, so the records here are built without it too.
 */

function untouched(Closure $build): Model
{
    return activity()->withoutLogs($build);
}

function logActivityOn(Model $subject): void
{
    activity()->performedOn($subject)->log('edited in qnet-2');
}

// AC-009 (guard part)
it('reports a commessa as touched by an entry on itself, a stage or a payment', function (string $where) {
    $guard = new LegacyRecordTouchGuard;
    $workOrder = untouched(fn () => WorkOrder::factory()->create());
    $stage = untouched(fn () => WorkOrderStage::factory()->create(['work_order_id' => $workOrder->id]));
    $payment = untouched(fn () => WorkOrderLinePayment::factory()->create(['work_order_id' => $workOrder->id]));
    $other = untouched(fn () => WorkOrder::factory()->create());

    expect($guard->isWorkOrderTouched($workOrder))->toBeFalse();

    logActivityOn(match ($where) {
        'work order' => $workOrder,
        'stage' => $stage,
        'payment' => $payment,
    });

    expect($guard->isWorkOrderTouched($workOrder))->toBeTrue()
        ->and($guard->isWorkOrderTouched($other))->toBeFalse();
})->with(['work order', 'stage', 'payment']);

// AC-018 (guard part)
it('reports an opportunity as touched only by an entry on itself', function () {
    $guard = new LegacyRecordTouchGuard;
    $opportunity = untouched(fn () => Opportunity::factory()->create());
    $other = untouched(fn () => Opportunity::factory()->create());

    expect($guard->isOpportunityTouched($opportunity))->toBeFalse();

    logActivityOn($opportunity);

    expect($guard->isOpportunityTouched($opportunity))->toBeTrue()
        ->and($guard->isOpportunityTouched($other))->toBeFalse();
});

// AC-012 (guard part)
it('reports a quote as touched by an entry on itself or on its opportunity', function (string $where) {
    $guard = new LegacyRecordTouchGuard;
    $quote = untouched(fn () => Quote::factory()->create());
    $sibling = untouched(fn () => Quote::factory()->create(['opportunity_id' => $quote->opportunity_id]));
    $unrelated = untouched(fn () => Quote::factory()->create());

    expect($guard->isQuoteTouched($quote))->toBeFalse();

    logActivityOn($where === 'quote' ? $quote : $quote->opportunity);

    expect($guard->isQuoteTouched($quote))->toBeTrue()
        ->and($guard->isQuoteTouched($sibling))->toBe($where === 'opportunity')
        ->and($guard->isQuoteTouched($unrelated))->toBeFalse();
})->with(['quote', 'opportunity']);

// AC-015 (guard part)
it('reports a contract as touched only by an entry on itself, not by its quote', function () {
    $guard = new LegacyRecordTouchGuard;
    $contract = untouched(fn () => Contract::factory()->create());

    expect($guard->isContractTouched($contract))->toBeFalse();

    logActivityOn($contract->quote);
    expect($guard->isContractTouched($contract))->toBeFalse();

    logActivityOn($contract);
    expect($guard->isContractTouched($contract))->toBeTrue();
});

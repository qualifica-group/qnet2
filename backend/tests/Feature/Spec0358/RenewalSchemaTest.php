<?php

use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0358 MT-01: period schema (AC-014) and per-commessa payments (AC-015).
 */
uses(RefreshDatabase::class);

it('AC-014: a pivot row defaults to the period 0', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 100);

    expect($workOrder->quoteLines()->first()->pivot->renewal_number)->toBe(0)
        ->and($line->workOrders()->first()->pivot->renewal_number)->toBe(0);
});

it('AC-014: the same line is allowed in two periods, never twice in the same one', function () {
    $quoteId = WorkOrder::factory()->create()->quote_id;
    $line = QuoteLine::factory()->create(['quote_id' => $quoteId]);
    $a = WorkOrder::factory()->create(['quote_id' => $quoteId]);
    $b = WorkOrder::factory()->create(['quote_id' => $quoteId]);
    $c = WorkOrder::factory()->create(['quote_id' => $quoteId]);

    attachLineInPeriod($a, $line, 0);
    attachLineInPeriod($b, $line, 1);

    expect($line->workOrders()->count())->toBe(2);
    expect(fn () => attachLineInPeriod($c, $line, 1))->toThrow(QueryException::class);
});

it('AC-014: the same line can have a payment row in two commesse, not twice in one', function () {
    $quoteId = WorkOrder::factory()->create()->quote_id;
    $line = QuoteLine::factory()->create(['quote_id' => $quoteId]);
    $a = WorkOrder::factory()->create(['quote_id' => $quoteId]);
    $b = WorkOrder::factory()->create(['quote_id' => $quoteId]);

    WorkOrderLinePayment::factory()->create(['work_order_id' => $a->id, 'quote_line_id' => $line->id]);
    WorkOrderLinePayment::factory()->create(['work_order_id' => $b->id, 'quote_line_id' => $line->id]);

    expect(WorkOrderLinePayment::count())->toBe(2);
    expect(fn () => WorkOrderLinePayment::factory()->create(['work_order_id' => $a->id, 'quote_line_id' => $line->id]))
        ->toThrow(QueryException::class);
});

it('AC-014: contract renewals are unique per contract number and exposed oldest first', function () {
    $contract = Contract::factory()->create();

    expect($contract->currentRenewalNumber())->toBe(0);

    renewContractTo($contract, 2);

    expect($contract->renewals->pluck('number')->all())->toBe([1, 2])
        ->and($contract->currentRenewalNumber())->toBe(2)
        ->and(fn () => ContractRenewal::factory()->create(['contract_id' => $contract->id, 'number' => 2]))
        ->toThrow(QueryException::class);
});

it('AC-014: the rollback fails with a readable message when a line is in several periods', function () {
    $quoteId = WorkOrder::factory()->create()->quote_id;
    $line = QuoteLine::factory()->create(['quote_id' => $quoteId]);
    attachLineInPeriod(WorkOrder::factory()->create(['quote_id' => $quoteId]), $line, 0);
    attachLineInPeriod(WorkOrder::factory()->create(['quote_id' => $quoteId]), $line, 1);

    $path = 'database/migrations/2026_11_20_100100_add_renewal_number_to_quote_line_work_order_table.php';
    $migration = require base_path($path);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, "quote_line_id(s) [{$line->id}]");
    expect(DB::table('quote_line_work_order')->count())->toBe(2);
});

it('AC-015: payments are per commessa for the same line in two periods', function () {
    $quoteId = WorkOrder::factory()->create()->quote_id;
    $line = QuoteLine::factory()->create(['quote_id' => $quoteId, 'net_amount' => 100, 'unit_price' => 100, 'total_amount' => 100, 'quantity' => 1]);
    $a = WorkOrder::factory()->create(['quote_id' => $quoteId]);
    $b = WorkOrder::factory()->create(['quote_id' => $quoteId]);
    attachLineInPeriod($a, $line, 0);
    attachLineInPeriod($b, $line, 1);
    $statusA = WorkOrderPaymentStatus::factory()->create();
    $statusB = WorkOrderPaymentStatus::factory()->create();
    WorkOrderLinePayment::factory()->create(['work_order_id' => $a->id, 'quote_line_id' => $line->id, 'work_order_payment_status_id' => $statusA->id]);
    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments', 'viewContractData']));

    $this->patchJson("/api/work-orders/{$b->id}/contract-data/lines/{$line->id}", ['work_order_payment_status_id' => $statusB->id, 'has_unpaid' => true])
        ->assertOk()
        ->assertJsonPath('data.payment.status.id', $statusB->id);

    expect(WorkOrderLinePayment::where('work_order_id', $a->id)->value('work_order_payment_status_id'))->toBe($statusA->id)
        ->and(WorkOrderLinePayment::where('work_order_id', $a->id)->value('has_unpaid'))->toBeFalsy();

    $this->getJson("/api/work-orders/{$a->id}/contract-data")->assertOk()
        ->assertJsonPath('data.lines.0.payment.status.id', $statusA->id);
    $this->getJson("/api/work-orders/{$b->id}/contract-data")->assertOk()
        ->assertJsonPath('data.lines.0.payment.status.id', $statusB->id)
        ->assertJsonPath('data.lines.0.payment.has_unpaid', true);
});

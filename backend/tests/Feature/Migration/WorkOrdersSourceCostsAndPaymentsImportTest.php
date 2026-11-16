<?php

use App\Models\CostType;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use Database\Seeders\WorkOrderPaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * `work-orders` source, spec 0189: a commessa without lines is not created,
 * the payment data lands on every line (G-24, spec 0201 D-5/D-14) and the
 * offer costs the legacy assigned to the commessa become its actual costs
 * (G-23). Fixtures in tests/Helpers/WorkOrderMigrationHelpers.php.
 */

beforeEach(function () {
    seedMigrationsConfig();
    $this->seed(WorkOrderPaymentStatusSeeder::class);
    $this->quote = Quote::factory()->create(['old_id' => 42]);
    $this->line = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 900, 'sort_order' => 0]);
});

// REQUIREMENT CHANGED (user decision 2026-10-14): what cannot be imported
// fails the work order with the reason, it is never skipped silently.
it('fails a work order whose lines cannot all be linked', function (array $legacyLineIds, string $reason) {
    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['quote_line_ids' => $legacyLineIds])]);

    expect($run->failed_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0)
        ->and(WorkOrder::query()->count())->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])->toBe("Failed to import the record: {$reason}");
})->with([
    'no legacy line' => [[], 'The work order has no quote line in the legacy; it is not imported.'],
    'one legacy line not migrated' => [[900, 555], 'Quote line (legacy id 555) not imported on this offer; the work order is not imported.'],
]);

it('writes the legacy payment data on every line of the work order, without activity log (G-24)', function () {
    $second = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 901, 'sort_order' => 1]);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord([
        'quote_line_ids' => [900, 901],
        'stato_pagamento' => 3,
        'accordo_pagamento' => ' Pagamento in due rate ',
        'insoluti' => true,
    ])], $actor);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $payments = WorkOrderLinePayment::query()->orderBy('quote_line_id')->get();

    expect($run->report)->toBeNull()
        ->and($payments->pluck('quote_line_id')->all())->toBe([$this->line->id, $second->id])
        ->and($payments->pluck('work_order_id')->unique()->all())->toBe([$workOrder->id])
        ->and($payments->pluck('work_order_payment_status_id')->unique()->all())->toBe([WorkOrderPaymentStatus::where('old_id', 3)->value('id')])
        ->and($payments->pluck('payment_agreement')->unique()->all())->toBe(['Pagamento in due rate'])
        ->and($payments->pluck('has_unpaid')->unique()->all())->toBe([true])
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('leaves an unknown legacy payment status empty with a warning, keeping the rest', function () {
    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['stato_pagamento' => 77, 'accordo_pagamento' => 'Bonifico'])]);

    $payment = WorkOrderLinePayment::query()->sole();

    expect($payment->work_order_payment_status_id)->toBeNull()
        ->and($payment->payment_agreement)->toBe('Bonifico')
        ->and(legacyWorkOrderWarnings($run))->toBe(['Unresolved payment status (legacy key 77); left empty.']);
});

it('imports the offer costs assigned to the work order as its actual costs (G-23)', function () {
    $costType = CostType::factory()->create();
    $expenseReport = Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 5, 'cost_type_id' => $costType->id]);
    $employeeLevel = Product::factory()->costOnly()->create(['old_source' => 'user_levels', 'old_id' => 6]);
    $vatRate = VatRate::factory()->create(['old_id' => 2, 'rate' => 22]);
    $supplier = Registry::factory()->supplier()->create(['old_id' => 70]);

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['costs' => [
        [
            'id' => 'quotationexpensereports:1', 'product_source' => 'expense_reports', 'product_id' => 5, 'quote_line_id' => 900,
            'description' => ' Docente ', 'quantity' => 40, 'unit_price' => 30, 'vat_rate_id' => 2, 'supplier_id' => 70,
            'incurred_on' => '2025-03-10', 'document_reference' => 'PF-12',
        ],
        [
            'id' => 'quotationemployees:19', 'product_source' => 'user_levels', 'product_id' => 6, 'quote_line_id' => null,
            'description' => null, 'quantity' => 8, 'unit_price' => 20, 'vat_rate_id' => null, 'supplier_id' => null,
            'incurred_on' => '2025-02-10', 'document_reference' => null,
        ],
    ]])]);

    $costs = WorkOrder::query()->where('old_id', 17)->sole()->costs()->get();

    expect($run->report)->toBeNull()
        ->and($costs)->toHaveCount(2)
        ->and($costs[0]->product_id)->toBe($expenseReport->id)
        ->and($costs[0]->quote_line_id)->toBe($this->line->id)
        ->and($costs[0]->cost_type_id)->toBe($costType->id)
        ->and((float) $costs[0]->net_amount)->toBe(1200.0)
        ->and((float) $costs[0]->vat_amount)->toBe(264.0)
        ->and($costs[0]->vat_rate_id)->toBe($vatRate->id)
        ->and($costs[0]->supplier_id)->toBe($supplier->id)
        ->and($costs[0]->incurred_on->toDateString())->toBe('2025-03-10')
        ->and($costs[0]->document_reference)->toBe('PF-12')
        ->and($costs[0]->additional_description)->toBe('Docente')
        ->and($costs[1]->product_id)->toBe($employeeLevel->id)
        ->and($costs[1]->quote_line_id)->toBeNull()
        ->and((float) $costs[1]->net_amount)->toBe(160.0)
        ->and($costs[1]->incurred_on->toDateString())->toBe('2025-02-10');
});

it('points a cost at the copy of a line already programmed into another work order', function () {
    Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 5]);
    WorkOrder::factory()->create(['quote_id' => $this->quote->id])->quoteLines()->attach($this->line->id);

    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['costs' => [[
        'id' => 'quotationexpensereports:1', 'product_source' => 'expense_reports', 'product_id' => 5, 'quote_line_id' => 900,
        'quantity' => 1, 'unit_price' => 10, 'incurred_on' => '2025-03-10',
    ]]])]);

    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();

    expect(WorkOrderCost::query()->sole()->quote_line_id)->toBe($workOrder->quoteLines()->sole()->id)
        ->and(WorkOrderCost::query()->sole()->quote_line_id)->not->toBe($this->line->id);
});

it('reports unresolved optional cost references, keeping the cost and the work order', function () {
    Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 5]);
    Registry::factory()->create(['old_id' => 71]);

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['costs' => [
        ['id' => 'a', 'product_source' => 'expense_reports', 'product_id' => 5, 'quote_line_id' => 777, 'quantity' => 1, 'unit_price' => 10, 'vat_rate_id' => 9, 'supplier_id' => 71, 'incurred_on' => '2025-03-10'],
    ]])]);

    $cost = WorkOrderCost::query()->sole();

    expect($run->created_rows)->toBe(1)
        ->and($cost->quote_line_id)->toBeNull()
        ->and($cost->vat_rate_id)->toBeNull()
        ->and($cost->supplier_id)->toBeNull()
        ->and(legacyWorkOrderWarnings($run))->toBe([
            'Cost a: its line (legacy id 777) is not on the work order; kept as an unattributed cost.',
            'Cost a: VAT rate (legacy id 9) not migrated; left empty.',
            'Cost a: supplier (legacy id 71) not migrated; left empty.',
        ]);
});

it('fails the work order when a cost has no usable migrated product', function (array $cost, string $reason) {
    Product::factory()->saleOnly()->create(['old_source' => 'expense_reports', 'old_id' => 8]);

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['costs' => [
        ['quantity' => 1, 'unit_price' => 10, 'incurred_on' => '2025-03-10', ...$cost],
    ]])]);

    expect($run->failed_rows)->toBe(1)
        ->and(WorkOrder::query()->count())->toBe(0)
        ->and(WorkOrderCost::query()->count())->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])->toBe("Failed to import the record: {$reason}");
})->with([
    'product not migrated' => [['id' => 'b', 'product_source' => 'user_levels', 'product_id' => 2], 'Cost b: cost product (user_levels legacy id 2) not migrated; the work order is not imported.'],
    'product not usable as a cost' => [['id' => 'c', 'product_source' => 'expense_reports', 'product_id' => 8], 'Cost c: product (expense_reports legacy id 8) is not usable as a cost; the work order is not imported.'],
    'no cost product' => [['id' => 'd', 'product_source' => 'vehicles_unknown', 'product_id' => 1], 'Cost d has no cost product; the work order is not imported.'],
]);

// G-27 (REQUIREMENT CHANGED, user decision 2026-10-20): the legacy sends its
// supervisors and participants as they are, deleted users included; one not
// migrated fails the row, a work order without any is imported.
it('fails a work order whose supervisor or participant is not migrated', function (string $field, string $role) {
    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord([$field => [69]])]);

    expect($run->failed_rows)->toBe(1)
        ->and(WorkOrder::query()->count())->toBe(0)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])
        ->toBe("Failed to import the record: {$role} (legacy user id 69) not migrated; the work order is not imported.");
})->with([
    'supervisor' => ['supervisor_user_ids', 'Supervisor'],
    'participant' => ['participant_user_ids', 'Participant'],
]);

it('imports a work order without supervisors', function () {
    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord(['supervisor_user_ids' => []])]);

    expect($run->failed_rows)->toBe(0)
        ->and(WorkOrder::query()->sole()->supervisors)->toBeEmpty();
});

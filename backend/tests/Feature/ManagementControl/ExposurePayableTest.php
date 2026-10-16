<?php

use App\Enums\PurchaseInvoiceType;
use App\Models\CommissionSettlement;
use App\Models\CostType;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0269, AC-008 / AC-009 — the payable side.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-008: the payable side replicates the receivable rules on the payable installments', function () {
    mcPayable([
        ['due' => -100, 'amount' => '100.00'], ['due' => -45, 'amount' => '200.00'], ['due' => -10, 'amount' => '300.00'],
        ['due' => 5, 'amount' => '400.00'], ['due' => 45, 'amount' => '500.00'], ['due' => 90, 'amount' => '600.00'],
        ['due' => -20, 'amount' => '999.00', 'paid' => '999.00'],
    ]);
    mcPayable([['due' => -40, 'amount' => '5.00', 'paid' => '5.00'], ['due' => -10, 'amount' => '30.00']]);
    mcPayable([['due' => -22, 'amount' => '9.00']]);
    mcPayable([['due' => 5, 'amount' => '10.00']]);
    mcPayable([['due' => -3, 'amount' => '15.00', 'paid' => '15.00']]);

    $side = mcGet('exposure')->assertOk()->json('data.payable');

    expect($side['aging'])->toEqual([
        'due_0_30' => 410, 'due_31_60' => 500, 'due_over_60' => 600,
        'overdue_1_30' => 339, 'overdue_31_60' => 200, 'overdue_61_90' => 0, 'overdue_over_90' => 100,
    ])->and($side['open_total'])->toEqual(2149)
        ->and($side['overdue_total'])->toEqual(639)
        ->and($side['not_due_total'])->toEqual(1510)
        ->and($side['by_status'])->toEqual([
            'paid' => ['documents' => 1, 'open' => 0],
            'not_due' => ['documents' => 1, 'open' => 10],
            'overdue' => ['documents' => 1, 'open' => 30],
            'seriously_overdue' => ['documents' => 2, 'open' => 2109],
        ]);
});

it('AC-008: the top suppliers, the flows of the period and the average days', function () {
    $first = Registry::factory()->supplier()->create(['name' => 'Fornitore Uno']);
    $second = Registry::factory()->supplier()->create(['name' => 'Fornitore Due']);
    mcPayable([['due' => -20, 'amount' => '500.00'], ['due' => 50, 'amount' => '80.00']], ['supplier_id' => $first->id, 'document_date' => mcDay(-10)]);
    mcPayable([['due' => -70, 'amount' => '300.00'], ['due' => -5, 'amount' => '100.00', 'paid' => '100.00', 'paid_at' => -5]], ['supplier_id' => $second->id, 'document_date' => mcDay(-105)]);
    mcPayable([['due' => 10, 'amount' => '60.00']]);

    $side = mcGet('exposure', ['date_from' => mcDay(-30), 'date_to' => mcDay(0), 'top' => 2])->assertOk()->json('data.payable');

    expect(array_column(array_column($side['top'], 'registry'), 'name'))->toBe(['Fornitore Uno', 'Fornitore Due'])
        ->and($side['top'][1])->toMatchArray(['open' => 300, 'overdue' => 300, 'worst_bucket' => 'overdue_61_90'])
        ->and($side['settled_in_period'])->toEqual(100)
        ->and($side['average_days'])->toBe(100)
        ->and($side['invoiced_in_period'])->toEqual(580);
});

it('AC-008: the invoiced amount is net of withholding and ENASARCO, the cost types are summed with "no type" last, statement invoices count', function () {
    $services = CostType::factory()->create(['name' => 'Servizi', 'sort_order' => 10, 'color' => 'blue']);
    $other = CostType::factory()->create(['name' => 'Licenze', 'sort_order' => 20]);
    mcPayable([['due' => -20, 'amount' => '300.00'], ['due' => 10, 'amount' => '550.00']], [
        'cost_type_id' => $services->id, 'withholding_amount' => '100.00', 'enasarco_amount' => '50.00', 'document_date' => mcDay(-10),
    ]);
    mcPayable([['due' => 20, 'amount' => '25.00']], ['cost_type_id' => $other->id, 'document_date' => mcDay(-200)]);
    $statement = mcPayable([['due' => -40, 'amount' => '120.00']], ['document_date' => mcDay(-200)]);
    CommissionSettlement::factory()->create(['purchase_invoice_id' => $statement->id]);

    $side = mcGet('exposure', ['date_from' => mcDay(-30), 'date_to' => mcDay(0)])->assertOk()->json('data.payable');

    expect($side['invoiced_in_period'])->toEqual(850)
        ->and($side['open_total'])->toEqual(995)
        ->and($side['overdue_total'])->toEqual(420)
        ->and(array_column($side['by_cost_type'], 'open'))->toEqual([850, 25, 120])
        ->and(array_column($side['by_cost_type'], 'overdue'))->toEqual([300, 0, 120])
        ->and($side['by_cost_type'][0]['cost_type'])->toBe(['id' => $services->id, 'name' => 'Servizi', 'color' => 'blue', 'nature' => 'direct'])
        ->and($side['by_cost_type'][1]['cost_type']['name'])->toBe('Licenze')
        ->and($side['by_cost_type'][2]['cost_type'])->toBeNull();
});

it('AC-009: the document type chooses proforma and invoices on the payable side, invoice by default', function () {
    mcPayable([['due' => 10, 'amount' => '100.00']]);
    mcPayable([['due' => 10, 'amount' => '30.00']], ['document_type' => PurchaseInvoiceType::Proforma]);

    $open = fn (array $params): float => mcGet('exposure', $params)->assertOk()->json('data.payable.open_total');

    expect($open([]))->toEqual(100)
        ->and($open(['document_type' => 'invoice']))->toEqual(100)
        ->and($open(['document_type' => 'proforma']))->toEqual(30)
        ->and($open(['document_type' => 'all']))->toEqual(130);
});

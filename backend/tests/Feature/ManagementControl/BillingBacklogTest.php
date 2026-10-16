<?php

use App\Enums\InvoiceType;
use App\Enums\SupplierCommissionDirection;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\Quote;
use App\Models\Referent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0271, AC-003 / AC-004 / AC-005 — the backlog of the open commesse.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-003: only the open commesse count, contract and invoiced are those of the income statement, the proforma are not invoiced', function () {
    $openCompany = Company::factory()->create();
    $open = mcWorkOrder([], ['company_id' => $openCompany->id]);
    mcRevenueLine($open, '1000.00');
    mcRevenueLine($open, '200.00', direction: SupplierCommissionDirection::Received);
    mcInvoice($open, InvoiceType::Invoice, '300.00', '366.00');
    mcInvoice($open, InvoiceType::Proforma, '500.00', '610.00');

    $overCompany = Company::factory()->create();
    $over = mcWorkOrder([], ['company_id' => $overCompany->id]);
    mcRevenueLine($over, '100.00');
    mcInvoice($over, InvoiceType::Invoice, '150.00', '183.00');

    $closed = mcWorkOrder(['is_force_closed' => true]);
    mcRevenueLine($closed, '9000.00');

    $data = mcGet('billing')->assertOk()->json('data.backlog');

    expect($data['work_orders_count'])->toBe(2)
        ->and($data['contract'])->toEqual(1100)
        ->and($data['invoiced'])->toEqual(450)
        ->and($data['to_invoice'])->toEqual(650);

    // A supplier-received line adds no contract revenue; the over-invoiced commessa alone is negative
    $alone = mcGet('billing', ['company_id' => [$overCompany->id]])->json('data.backlog');
    expect($alone['to_invoice'])->toEqual(-50)->and($alone['work_orders_count'])->toBe(1);

    // The income statement of the same two commesse shows the same contract and invoiced
    $statement = mcGet('income-statement', ['company_id' => [$openCompany->id, $overCompany->id]])->json('data.totals');
    expect($statement['revenue_contract'])->toEqual($data['contract'])->and($statement['revenue_invoiced'])->toEqual($data['invoiced']);
});

it('AC-004: by business function the commessa is split by function shares with the rest on the last one', function () {
    $alpha = BusinessFunction::factory()->create(['name' => 'Alpha function']);
    $beta = BusinessFunction::factory()->create(['name' => 'Beta function']);
    $split = mcWorkOrder();
    mcRevenueLine($split, '100.00', $alpha);
    mcRevenueLine($split, '200.00', $beta);
    mcInvoice($split, InvoiceType::Invoice, '100.00', '100.00');
    $plain = mcWorkOrder();
    mcRevenueLine($plain, '50.00', $alpha);
    mcWorkOrder();

    $data = mcGet('billing')->assertOk()->json('data.backlog');
    $rows = collect($data['by_dimension'])->keyBy('key');

    expect(array_column($data['by_dimension'], 'key'))->toBe([(string) $alpha->id, (string) $beta->id, 'unattributed'])
        ->and(array_column($data['by_dimension'], 'label'))->toBe(['Alpha function', 'Beta function', null])
        ->and($rows[(string) $alpha->id]['work_orders_count'])->toBe(2)
        ->and($rows[(string) $alpha->id]['contract'])->toEqual(150)
        ->and($rows[(string) $alpha->id]['invoiced'])->toEqual(33.33)
        ->and($rows[(string) $alpha->id]['to_invoice'])->toEqual(116.67)
        ->and($rows[(string) $beta->id]['contract'])->toEqual(200)
        ->and($rows[(string) $beta->id]['invoiced'])->toEqual(66.67)
        ->and($rows['unattributed']['work_orders_count'])->toBe(1)
        ->and($data['work_orders_count'])->toBe(3);

    expect(round(collect($data['by_dimension'])->sum('contract'), 2))->toEqual($data['contract'])
        ->and(round(collect($data['by_dimension'])->sum('invoiced'), 2))->toEqual($data['invoiced'])
        ->and(round(collect($data['by_dimension'])->sum('to_invoice'), 2))->toEqual($data['to_invoice']);
});

it('AC-004: by company and commercial the dimension is that of the reference offer, none goes last', function () {
    $alfa = Company::factory()->create(['denomination' => 'Alfa']);
    $beta = Company::factory()->create(['denomination' => 'Beta']);
    $mario = Referent::factory()->create(['name' => 'Mario']);
    $first = mcWorkOrder([], ['company_id' => $alfa->id, 'commercial_id' => $mario->id]);
    mcRevenueLine($first, '100.00');
    $second = mcWorkOrder([], ['company_id' => $beta->id, 'commercial_id' => null]);
    mcRevenueLine($second, '40.00');
    mcInvoice($second, InvoiceType::Invoice, '10.00', '10.00');

    $byCompany = mcGet('billing', ['group_by' => 'company'])->assertOk()->json('data.backlog.by_dimension');
    $byCommercial = mcGet('billing', ['group_by' => 'commercial'])->assertOk()->json('data.backlog.by_dimension');

    expect(array_column($byCompany, 'label'))->toBe(['Alfa', 'Beta'])
        ->and($byCompany[1]['work_orders_count'])->toBe(1)
        ->and($byCompany[1]['to_invoice'])->toEqual(30)
        ->and(array_column($byCommercial, 'key'))->toBe([(string) $mario->id, 'unattributed'])
        ->and($byCommercial[1]['label'])->toBeNull()
        ->and($byCommercial[1]['contract'])->toEqual(40);
});

it('AC-004: on a multi-offer commessa the reference offer is the one with the lowest id', function () {
    $alfa = Company::factory()->create(['denomination' => 'Alfa']);
    $beta = Company::factory()->create(['denomination' => 'Beta']);
    $lowQuote = Quote::factory()->create(['company_id' => $alfa->id]);
    $highQuote = Quote::factory()->create(['company_id' => $beta->id]);
    $workOrder = mcWorkOrder(['quote_id' => null]);
    mcRevenueLine($workOrder, '10.00', quote: $highQuote);
    mcRevenueLine($workOrder, '10.00', quote: $lowQuote);

    $rows = mcGet('billing', ['group_by' => 'company'])->assertOk()->json('data.backlog.by_dimension');

    expect(array_column($rows, 'label'))->toBe(['Alfa'])->and($rows[0]['contract'])->toEqual(20);
});

it('AC-005: the commesse fall in the age bucket of their start date', function () {
    foreach ([[1, '10.00'], [4, '20.00'], [8, '40.00'], [15, '80.00'], [2, '1.00']] as [$months, $amount]) {
        mcRevenueLine(mcWorkOrder(['start_date' => now()->subMonthsNoOverflow($months)->toDateString()]), $amount);
    }

    $data = mcGet('billing')->assertOk()->json('data.backlog');

    expect($data['by_age'])->toEqual([
        ['bucket' => '0_3', 'work_orders_count' => 2, 'to_invoice' => 11],
        ['bucket' => '3_6', 'work_orders_count' => 1, 'to_invoice' => 20],
        ['bucket' => '6_12', 'work_orders_count' => 1, 'to_invoice' => 40],
        ['bucket' => 'over_12', 'work_orders_count' => 1, 'to_invoice' => 80],
    ])->and($data['work_orders_count'])->toBe(5)->and($data['to_invoice'])->toEqual(151);
});

it('AC-003: the company filter restricts the commesse via the reference offer', function () {
    $alfa = Company::factory()->create();
    $beta = Company::factory()->create();
    mcRevenueLine(mcWorkOrder([], ['company_id' => $alfa->id]), '10.00');
    mcRevenueLine(mcWorkOrder([], ['company_id' => $beta->id]), '20.00');

    $data = mcGet('billing', ['company_id' => [$beta->id]])->assertOk()->json('data.backlog');

    expect($data['work_orders_count'])->toBe(1)->and($data['contract'])->toEqual(20);
});

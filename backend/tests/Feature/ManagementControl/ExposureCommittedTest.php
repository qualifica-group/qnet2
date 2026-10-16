<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\CostType;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0269, AC-010 / AC-011 / AC-012 — the commitments and the net exposure.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-010: the commitments are the not billed amount of the invoiceable orders, by supplier and cost type', function () {
    $commessa = mcWorkOrder();
    $services = CostType::factory()->create(['name' => 'Servizi', 'sort_order' => 20]);
    $materials = CostType::factory()->create(['name' => 'Materiali', 'sort_order' => 10]);
    [$alfa, $beta, $gamma] = collect(['Alfa', 'Beta', 'Gamma'])->map(fn (string $name): Registry => Registry::factory()->supplier()->create(['name' => $name]))->all();

    $partlyBilled = mcOrderLine($commessa, '100.00', PurchaseOrderStatus::Approved, ['supplier_id' => $alfa->id], ['cost_type_id' => $services->id]);
    mcPayableLine($commessa, '40.00', $partlyBilled, '0.400');
    mcOrderLine(null, '50.00', PurchaseOrderStatus::Fulfilled, ['supplier_id' => $alfa->id], ['cost_type_id' => $materials->id]);
    mcOrderLine(null, '80.00', PurchaseOrderStatus::SentToSupplier, ['supplier_id' => $beta->id], ['cost_type_id' => $materials->id]);
    mcOrderLine(null, '10.00', PurchaseOrderStatus::Approved, ['supplier_id' => $gamma->id], ['cost_type_id' => $services->id]);
    mcOrderLine(null, '999.00', PurchaseOrderStatus::Draft, ['supplier_id' => $gamma->id]);

    $committed = mcGet('exposure', ['top' => 2])->assertOk()->json('data.committed');

    expect($committed)->toMatchArray(['total' => 200, 'with_work_order' => 60, 'without_work_order' => 140])
        ->and($committed['with_work_order'] + $committed['without_work_order'])->toEqual($committed['total'])
        ->and(array_column(array_column($committed['by_supplier'], 'registry'), 'name'))->toBe(['Alfa', 'Beta'])
        ->and($committed['by_supplier'][0])->toMatchArray(['committed' => 110, 'orders' => 2])
        ->and($committed['by_supplier'][1])->toMatchArray(['committed' => 80, 'orders' => 1])
        ->and(array_column(array_column($committed['by_cost_type'], 'cost_type'), 'name'))->toBe(['Materiali', 'Servizi'])
        ->and(array_column($committed['by_cost_type'], 'open'))->toEqual([130, 70])
        ->and(array_column($committed['by_cost_type'], 'overdue'))->toEqual([0, 0]);
});

it('AC-011: the net exposure by dimension has one row per value and sums to the totals', function () {
    mcExposureScenario();

    $rows = fn (string $groupBy): array => mcGet('exposure', ['group_by' => $groupBy])->assertOk()->json('data.exposure.by_dimension');
    $view = fn (array $list): array => array_map(fn (array $row): array => [$row['key'] === 'unattributed' ? null : $row['label'], $row['receivable_open'], $row['payable_open'], $row['committed'], $row['net']], $list);

    $company = $view($rows('company'));
    $site = $view($rows('company_site'));
    $operational = $view($rows('operational_site'));

    expect($company)->toEqual([['Alfa', 150, 30, 40, 80], ['Beta', 200, 20, 70, 110]])
        ->and($site)->toEqual([['Sede 1', 100, 30, 40, 30], ['Sede 2', 200, 20, 70, 110], [null, 50, 0, 0, 50]])
        ->and($operational)->toEqual([['Roma', 200, 0, 70, 130], ['Torino', 100, 30, 40, 30], [null, 50, 20, 0, 30]]);

    $unattributed = collect($rows('company_site'))->last();
    expect($unattributed)->toMatchArray(['key' => 'unattributed', 'label' => null]);

    $exposure = mcGet('exposure', ['group_by' => 'operational_site'])->json('data.exposure');
    expect($exposure)->toMatchArray(['receivable_open' => 350, 'payable_open' => 50, 'committed_total' => 110, 'net' => 190]);
    foreach (['receivable_open', 'payable_open', 'net'] as $field) {
        expect(array_sum(array_column($exposure['by_dimension'], $field)))->toEqual($exposure[$field]);
    }
    expect(array_sum(array_column($exposure['by_dimension'], 'committed')))->toEqual($exposure['committed_total'])
        ->and($exposure['net'])->toEqual($exposure['receivable_open'] - $exposure['payable_open'] - $exposure['committed_total']);
});

it('AC-012: company and site filters keep every section on the filtered documents only', function () {
    $scenario = mcExposureScenario();
    $figures = function (array $params): array {
        $data = mcGet('exposure', $params)->assertOk()->json('data');

        return [$data['receivable']['open_total'], $data['payable']['open_total'], $data['committed']['total'], $data['exposure']['net']];
    };

    expect($figures([]))->toEqual([350, 50, 110, 190])
        ->and($figures(['company_id' => [$scenario['alfa']->id]]))->toEqual([150, 30, 40, 80])
        ->and($figures(['company_site_id' => [$scenario['siteTwo']->id]]))->toEqual([200, 20, 70, 110])
        ->and($figures(['operational_site_id' => [$scenario['torino']->id]]))->toEqual([100, 30, 40, 30])
        ->and($figures(['company_id' => [$scenario['alfa']->id], 'operational_site_id' => [$scenario['roma']->id]]))->toEqual([0, 0, 0, 0]);

    $filtered = mcGet('exposure', ['company_id' => [$scenario['beta']->id]])->json('data');
    expect($filtered['receivable']['aging']['due_0_30'])->toEqual(200)
        ->and(array_column($filtered['exposure']['by_dimension'], 'label'))->toBe(['Beta'])
        ->and($filtered['concentration']['invoiced_total'])->toEqual(200);
});

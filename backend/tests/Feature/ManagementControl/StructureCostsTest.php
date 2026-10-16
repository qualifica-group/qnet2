<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\OperationalSite;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0268, AC-011 / AC-012 — GET /api/management-control/structure-costs.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-011: only the open order lines and document lines without commessa count, statement invoices excluded', function () {
    $first = CostType::factory()->create(['name' => 'Servizi', 'sort_order' => 10]);
    $second = CostType::factory()->create(['name' => 'Materiali', 'sort_order' => 20]);
    $commessa = mcWorkOrder();

    $partlyBilled = mcOrderLine(null, '100.00', PurchaseOrderStatus::Approved, [], ['cost_type_id' => $second->id]);
    mcPayableLine(null, '40.00', $partlyBilled, '0.400', [], null, ['cost_type_id' => $second->id]);
    mcOrderLine(null, '50.00', PurchaseOrderStatus::SentToSupplier, [], ['cost_type_id' => $first->id]);
    mcOrderLine(null, '999.00', PurchaseOrderStatus::Draft);
    mcPayableLine(null, '25.00', null, '1.000', [], null, ['cost_type_id' => $first->id]);
    mcOrderLine($commessa, '77.00');
    mcPayableLine($commessa, '33.00');
    $statement = PurchaseInvoice::factory()->create();
    mcPayableLine(null, '500.00', null, '1.000', [], $statement);
    CommissionSettlement::factory()->create(['purchase_invoice_id' => $statement->id]);

    $data = mcGet('structure-costs')->assertOk()->json('data');

    expect($data['totals'])->toEqual(['committed_net' => 110, 'invoiced_net' => 65, 'total_net' => 175])
        ->and(array_column(array_column($data['by_cost_type'], 'cost_type'), 'name'))->toBe(['Servizi', 'Materiali'])
        ->and($data['by_cost_type'][0]['cost_type']['nature'])->toBe('direct')
        ->and($data['by_cost_type'][0])->toMatchArray(['committed_net' => 50.0, 'invoiced_net' => 25.0, 'total_net' => 75.0])
        ->and($data['by_cost_type'][1])->toMatchArray(['committed_net' => 60.0, 'invoiced_net' => 40.0, 'total_net' => 100.0])
        ->and($data['applied']['group_by'])->toBe('company');
});

it('AC-012: period, company and site filters and the grouping follow the document headers', function () {
    $companyOne = Company::factory()->create(['denomination' => 'Alfa']);
    $companyTwo = Company::factory()->create(['denomination' => 'Beta']);
    $siteOne = CompanySite::factory()->create(['company_id' => $companyOne->id, 'name' => 'Sede 1']);
    $siteTwo = CompanySite::factory()->create(['company_id' => $companyTwo->id, 'name' => 'Sede 2']);
    $operational = OperationalSite::factory()->create(['alias' => 'Torino']);

    mcOrderLine(null, '100.00', PurchaseOrderStatus::Approved, ['company_id' => $companyOne->id, 'company_site_id' => $siteOne->id, 'order_date' => '2026-02-10']);
    mcOrderLine(null, '200.00', PurchaseOrderStatus::Approved, ['company_id' => $companyTwo->id, 'company_site_id' => $siteTwo->id, 'operational_site_id' => $operational->id, 'order_date' => '2026-03-10']);
    mcPayableLine(null, '50.00', null, '1.000', ['company_id' => $companyOne->id, 'document_date' => '2026-02-20']);

    $total = fn (array $params): float => mcGet('structure-costs', $params)->assertOk()->json('data.totals.total_net');
    $labels = fn (string $groupBy): array => array_map(
        fn (array $row): array => [$row['label'], $row['total_net']],
        mcGet('structure-costs', ['group_by' => $groupBy])->assertOk()->json('data.by_dimension'),
    );

    expect($total([]))->toEqual(350)
        ->and($total(['date_from' => '2026-02-01', 'date_to' => '2026-02-28']))->toEqual(150)
        ->and($total(['company_id' => [$companyTwo->id]]))->toEqual(200)
        ->and($total(['company_site_id' => [$siteOne->id]]))->toEqual(100)
        ->and($total(['operational_site_id' => [$operational->id]]))->toEqual(200)
        ->and($labels('company'))->toEqual([['Alfa', 150], ['Beta', 200]])
        ->and($labels('company_site'))->toEqual([['Sede 1', 100], ['Sede 2', 200], [null, 50]])
        ->and($labels('operational_site'))->toEqual([['Torino', 200], [null, 150]]);

    mcGet('structure-costs', ['group_by' => 'cost_type'])->assertUnprocessable();
    mcGet('structure-costs', ['date_from' => '2026-03-01', 'date_to' => '2026-02-01'])->assertUnprocessable();
    mcGet('structure-costs', ['company_id' => [999999]])->assertUnprocessable();
});

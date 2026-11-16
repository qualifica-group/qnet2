<?php

declare(strict_types=1);

use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Tasks 427625 and 427940: the product line (root category) and product category columns of
 * the Report Vendite Agenti, the taxable amount net of the reporter's commission, and the
 * `view` row action that opens the offer.
 */
uses(RefreshDatabase::class);

/**
 * @return array{iso: ProductCategory, iso14001: ProductCategory, soa: ProductCategory, soaConsulting: ProductCategory}
 */
function salesReportCategoryTree(): array
{
    $iso = ProductCategory::factory()->create(['name' => 'ISO']);
    $soa = ProductCategory::factory()->create(['name' => 'SOA']);

    return [
        'iso' => $iso,
        'iso14001' => ProductCategory::factory()->childOf($iso)->create(['name' => 'ISO - 14001']),
        'soa' => $soa,
        'soaConsulting' => ProductCategory::factory()->childOf($soa)->create(['name' => 'SOA - Consulenza']),
    ];
}

function salesReportQuoteSelling(ProductCategory $category, string $title): Quote
{
    $quote = agentSalesReportQuote(attributes: ['title' => $title]);
    agentSalesReportLine($quote, ['product_id' => Product::factory()->create(['category_id' => $category->id])->id]);

    return $quote;
}

/**
 * An offer with no product yet: its categories come from its Opportunita's product lines.
 */
function salesReportQuoteWithoutLines(ProductCategory $category, string $title): Quote
{
    $quote = agentSalesReportQuote(attributes: ['title' => $title]);
    OpportunityProductLine::factory()->create(['opportunity_id' => $quote->opportunity_id, 'product_category_id' => $category->id]);

    return $quote;
}

/**
 * @param  array<string, mixed>  $state
 * @return array<string, array<string, mixed>>
 */
function salesReportRowsByTitle(mixed $test, array $state = []): array
{
    return collect(agentSalesReportRows($test, $state))->keyBy('title')->all();
}

it('projects the product line and category from the revenue lines, else from the opportunity', function () {
    $tree = salesReportCategoryTree();
    salesReportQuoteSelling($tree['iso14001'], 'Venduta');
    salesReportQuoteWithoutLines($tree['soaConsulting'], 'Senza righe');
    agentSalesReportQuote(attributes: ['title' => 'Vuota']);
    Sanctum::actingAs(agentSalesReportActor());

    $rows = salesReportRowsByTitle($this);

    expect($rows['Venduta'])->toMatchArray(['root_category' => 'ISO', 'category' => 'ISO - 14001'])
        ->and($rows['Senza righe'])->toMatchArray(['root_category' => 'SOA', 'category' => 'SOA - Consulenza'])
        ->and($rows['Vuota'])->toMatchArray(['root_category' => null, 'category' => null]);
});

it('ignores the opportunity product lines when the offer has its own revenue lines', function () {
    $tree = salesReportCategoryTree();
    $quote = salesReportQuoteSelling($tree['iso14001'], 'Venduta');
    OpportunityProductLine::factory()->create(['opportunity_id' => $quote->opportunity_id, 'product_category_id' => $tree['soaConsulting']->id]);
    Sanctum::actingAs(agentSalesReportActor());

    expect(salesReportRowsByTitle($this)['Venduta'])->toMatchArray(['root_category' => 'ISO', 'category' => 'ISO - 14001']);
});

it('filters by product line and by category with the same fallback, blank entry included', function () {
    $tree = salesReportCategoryTree();
    salesReportQuoteSelling($tree['iso14001'], 'Venduta ISO');
    salesReportQuoteWithoutLines($tree['soaConsulting'], 'SOA senza righe');
    agentSalesReportQuote(attributes: ['title' => 'Vuota']);
    Sanctum::actingAs(agentSalesReportActor());

    $byRoot = fn (array $values): array => array_keys(salesReportRowsByTitle($this, ['filterModel' => ['root_category' => ['filterType' => 'set', 'values' => $values]]]));
    $byCategory = fn (array $values): array => array_keys(salesReportRowsByTitle($this, ['filterModel' => ['category' => ['filterType' => 'set', 'values' => $values]]]));

    expect($byRoot(['ISO']))->toBe(['Venduta ISO'])
        ->and($byRoot(['SOA']))->toBe(['SOA senza righe'])
        ->and($byCategory(['SOA - Consulenza']))->toBe(['SOA senza righe'])
        ->and($byRoot([null]))->toBe(['Vuota']);
});

it('lists the product lines and categories as distinct values, with the blank entry', function () {
    $tree = salesReportCategoryTree();
    salesReportQuoteSelling($tree['iso14001'], 'Venduta ISO');
    salesReportQuoteWithoutLines($tree['soaConsulting'], 'SOA senza righe');
    agentSalesReportQuote(attributes: ['title' => 'Vuota']);
    Sanctum::actingAs(agentSalesReportActor());

    $values = fn (string $columnId): array => $this->postJson('/api/tables/agent-sales-report/values', ['columnId' => $columnId])
        ->assertOk()->json('data.values');

    expect($values('root_category'))->toBe([null, 'ISO', 'SOA'])
        ->and($values('category'))->toBe([null, 'ISO - 14001', 'SOA - Consulenza']);
});

it('takes only the reporter commissions out of the taxable amount', function () {
    $quote = agentSalesReportQuote(attributes: ['title' => 'Con segnalatore', 'revenue_net' => 2000]);
    agentSalesReportLine($quote, [], ['REPORTER' => 40, 'COMMERCIAL' => 70, 'SUPERVISOR' => 10, 'SUPPLIER' => 60]);
    agentSalesReportLine($quote, [], ['REPORTER' => 15.5]);
    agentSalesReportQuote(attributes: ['title' => 'Senza segnalatore', 'revenue_net' => 300]);
    Sanctum::actingAs(agentSalesReportActor());

    $rows = salesReportRowsByTitle($this);

    expect($rows['Con segnalatore']['revenue_net_after_reporter'])->toEqual(1944.5)
        ->and($rows['Senza segnalatore']['revenue_net_after_reporter'])->toEqual(300);
});

it('offers the view action only to whoever can open offers', function () {
    agentSalesReportQuote();
    Sanctum::actingAs(agentSalesReportActor());
    expect(agentSalesReportRows($this)[0]['actions'])->toBe([]);

    Sanctum::actingAs(agentSalesReportActor()->givePermissionTo(Permission::findOrCreate('quotes.view', 'web')));
    expect(agentSalesReportRows($this)[0]['actions'])->toBe(['view']);
});

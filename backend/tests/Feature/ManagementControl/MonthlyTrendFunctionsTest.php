<?php

use App\Enums\InvoiceType;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0270, AC-007 — revenue and cost by business function, month by month.
uses(RefreshDatabase::class);

const TREND_PERIOD = ['date_from' => '2026-01-01', 'date_to' => '2026-03-31'];

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-007: every amount goes to the effective function, the commessa shares split the rest and the groups add up to the totals', function () {
    $alpha = BusinessFunction::factory()->create(['name' => 'Alpha function']);
    $beta = BusinessFunction::factory()->create(['name' => 'Beta function']);
    $workOrder = mcWorkOrder();
    $lineAlpha = mcRevenueLine($workOrder, '300.00', $alpha);
    $lineBeta = mcRevenueLine($workOrder, '100.00', $beta);

    // A line whose category inherits the function of its parent
    $parent = ProductCategory::factory()->create(['business_function_id' => $alpha->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id, 'business_function_id' => null]);
    $inherited = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id, 'product_id' => Product::factory()->create(['category_id' => $child->id])->id]);

    mcTrendInvoice('2026-01-10', [['100.00', $lineAlpha], ['50.00', $lineBeta], ['20.00', $inherited]]);
    mcTrendInvoice('2026-02-10', [['100.01', null]], ['work_order_id' => $workOrder->id]);
    mcTrendInvoice('2026-03-10', [['40.00', null]]);
    mcTrendInvoice('2026-01-12', [['999.00', $lineAlpha]], type: InvoiceType::Proforma);

    $emptyWorkOrder = mcWorkOrder();
    mcTrendCost('2026-01-15', '80.00', $workOrder);
    mcTrendCost('2026-01-16', '30.00');
    mcTrendCost('2026-02-15', '10.00', $emptyWorkOrder);

    $data = mcGet('monthly-trend', TREND_PERIOD)->assertOk()->json('data');
    $rows = collect($data['functions'])->keyBy('key');

    expect($data['months'])->toBe(['2026-01', '2026-02', '2026-03'])
        ->and(array_column($data['functions'], 'key'))->toBe([(string) $alpha->id, (string) $beta->id, 'structure', 'unattributed'])
        ->and(array_column($data['functions'], 'label'))->toBe(['Alpha function', 'Beta function', null, null])
        ->and($rows[(string) $alpha->id]['revenue'])->toEqual([120, 75.01, 0])
        ->and($rows[(string) $alpha->id]['cost'])->toEqual([60, 0, 0])
        ->and($rows[(string) $alpha->id]['margin'])->toEqual([60, 75.01, 0])
        ->and($rows[(string) $beta->id]['revenue'])->toEqual([50, 25, 0])
        ->and($rows[(string) $beta->id]['cost'])->toEqual([20, 0, 0])
        ->and($rows['structure']['cost'])->toEqual([30, 0, 0])
        ->and($rows['structure']['revenue'])->toEqual([0, 0, 0])
        ->and($rows['unattributed']['revenue'])->toEqual([0, 0, 40])
        ->and($rows['unattributed']['cost'])->toEqual([0, 10, 0])
        ->and($data['totals']['revenue'])->toEqual([170, 100.01, 40])
        ->and($data['totals']['cost'])->toEqual([110, 10, 0])
        ->and($data['totals']['margin'])->toEqual([60, 90.01, 40]);

    foreach (['revenue', 'cost', 'margin'] as $measure) {
        foreach ($data['months'] as $index => $month) {
            expect(round(array_sum(array_map(fn (array $row): float => $row[$measure][$index], $data['functions'])), 2))->toEqual($data['totals'][$measure][$index]);
        }
    }
});

it('AC-007: the company and site filters restrict revenue (via the offer) and costs (via the header)', function () {
    $alfa = Company::factory()->create();
    $beta = Company::factory()->create();
    $siteAlfa = CompanySite::factory()->create(['company_id' => $alfa->id]);
    $siteBeta = CompanySite::factory()->create(['company_id' => $beta->id]);
    $quoteAlfa = Quote::factory()->create(['company_id' => $alfa->id, 'company_site_id' => $siteAlfa->id]);
    $quoteBeta = Quote::factory()->create(['company_id' => $beta->id, 'company_site_id' => $siteBeta->id]);

    mcTrendInvoice('2026-01-10', [['100.00', null]], ['company_id' => $alfa->id, 'quote_id' => $quoteAlfa->id]);
    mcTrendInvoice('2026-01-10', [['200.00', null]], ['company_id' => $beta->id, 'quote_id' => $quoteBeta->id]);
    mcTrendCost('2026-01-10', '10.00', document: ['company_id' => $alfa->id, 'company_site_id' => $siteAlfa->id]);
    mcTrendCost('2026-01-10', '20.00', document: ['company_id' => $beta->id, 'company_site_id' => $siteBeta->id]);

    $totals = fn (array $filters): array => mcGet('monthly-trend', [...TREND_PERIOD, ...$filters])->assertOk()->json('data.totals');

    expect($totals([])['revenue'][0])->toEqual(300)
        ->and($totals(['company_id' => [$alfa->id]]))->toMatchArray(['revenue' => [100, 0, 0], 'cost' => [10, 0, 0]])
        ->and($totals(['company_site_id' => [$siteBeta->id]]))->toMatchArray(['revenue' => [200, 0, 0], 'cost' => [20, 0, 0]])
        ->and($totals(['company_id' => [$alfa->id], 'company_site_id' => [$siteBeta->id]])['revenue'])->toEqual([0, 0, 0]);
});

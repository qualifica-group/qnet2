<?php

use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\WorkOrdersTarget;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Spec 0375 AC-015 on the commessa: costs meet on product and imputed line (D-13).

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function realignLegacyCost(array $overrides = []): array
{
    return [
        'id' => 'quotationexpensereports:1', 'product_source' => 'expense_reports', 'product_id' => 5, 'quote_line_id' => 900,
        'description' => 'Docente', 'quantity' => 40, 'unit_price' => 30, 'vat_rate_id' => null, 'supplier_id' => null,
        'incurred_on' => '2025-03-10', 'document_reference' => 'PF-12', ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $costs
 */
function realignWorkOrderCosts(array $costs, bool $apply = true): LegacyRealignReport
{
    Http::swap(new HttpFactory);
    fakeLegacyRealign('work-orders', [17], [], [legacyWorkOrderRecord(['costs' => $costs])]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(WorkOrdersTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

function importedWorkOrderCosts(): Collection
{
    return WorkOrder::query()->where('old_id', 17)->sole()->costs()->get();
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->quote = Quote::factory()->create(['old_id' => 42]);
    $this->line = QuoteLine::factory()->create(['quote_id' => $this->quote->id, 'old_id' => 900, 'sort_order' => 0]);
    $this->product = Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 5]);
});

it('AC-015: a cost with a single match is updated and its amounts recomputed', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['costs' => [realignLegacyCost()]])]);

    realignWorkOrderCosts([realignLegacyCost(['quantity' => 50])]);

    $cost = importedWorkOrderCosts()->sole();

    expect((float) $cost->quantity)->toBe(50.0)->and((float) $cost->net_amount)->toBe(1500.0);
});

it('AC-015: a cost absent on the commessa is created', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);

    realignWorkOrderCosts([realignLegacyCost()]);

    expect(importedWorkOrderCosts()->sole()->product_id)->toBe($this->product->id);
});

it('AC-015: two candidates with different values are only reported', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['costs' => [realignLegacyCost(['unit_price' => 10]), realignLegacyCost(['id' => 'quotationexpensereports:2', 'unit_price' => 20])]])]);

    $report = realignWorkOrderCosts([realignLegacyCost(['id' => 'quotationexpensereports:3', 'unit_price' => 99])]);

    expect(importedWorkOrderCosts()->pluck('unit_price')->map(fn ($p) => (float) $p)->all())->toBe([10.0, 20.0])
        ->and($report->messages[0][3])->toContain('quotationexpensereports:3');
});

it('AC-015: a cost entered by hand keeps its values, also when it meets a legacy cost', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);
    $workOrder = WorkOrder::query()->where('old_id', 17)->sole();
    $manual = WorkOrderCost::factory()->create([
        'work_order_id' => $workOrder->id, 'product_id' => $this->product->id, 'quote_line_id' => $this->line->id, 'quantity' => 1, 'unit_price' => 5,
    ]);

    $report = realignWorkOrderCosts([realignLegacyCost()]);

    expect(importedWorkOrderCosts()->sole()->id)->toBe($manual->id)
        ->and((float) $manual->fresh()->unit_price)->toBe(5.0)
        ->and($report->messages)->toHaveCount(1);
});

it('does not write when the costs are identical', function () {
    runLegacyWorkOrdersImport([legacyWorkOrderRecord(['costs' => [realignLegacyCost()]])]);
    $report = realignWorkOrderCosts([realignLegacyCost()]);

    expect($report->counters['work-orders'][LegacyRealignReport::UNCHANGED])->toBe(1);
});

it('simulates a new cost without leaving a row', function () {
    VatRate::factory()->create(['old_id' => 2, 'rate' => 22]);
    runLegacyWorkOrdersImport([legacyWorkOrderRecord()]);

    $report = realignWorkOrderCosts([realignLegacyCost(['vat_rate_id' => 2])], apply: false);

    expect(importedWorkOrderCosts())->toHaveCount(0)
        ->and(collect($report->changes)->pluck(4)->all())->toBe(['costs']);
});

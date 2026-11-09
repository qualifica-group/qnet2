<?php

use App\Enums\QuoteLineType;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\QuotesTarget;
use App\Models\InvoiceLine;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Spec 0375 AC-013, AC-014 and AC-015 on the offer. A line older than --since never moves (D-12).

const REALIGN_RECENT = '2026-10-25 09:00:00';
const REALIGN_OLD = '2026-10-01 09:00:00';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function realignOfferLine(array $overrides = []): array
{
    return ['id' => 900, 'product_id' => 10, 'description' => null, 'quantity' => 2, 'unit_price' => 50, 'vat_rate_id' => null, 'commissions' => [], 'updated_at' => REALIGN_RECENT, ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function realignOfferCostLine(array $overrides = []): array
{
    return [
        'id' => 'quotationexpensereports:1', 'product_source' => 'expense_reports', 'product_id' => 5, 'description' => null,
        'quantity' => 1, 'unit_price' => 10, 'vat_rate_id' => null, 'supplier_id' => null, 'offer_line_id' => 900, 'updated_at' => REALIGN_RECENT, ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function realignQuoteLines(array $overrides, bool $apply = true): LegacyRealignReport
{
    Http::swap(new HttpFactory);
    fakeLegacyRealign('quotes', [42], [], [legacyQuoteRecord(['lines' => [realignOfferLine()], ...$overrides])]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(QuotesTarget::class)->changedSince('2026-10-23'), '2026-10-23', $apply, $report);

    return $report;
}

function realignedLine(int $oldId = 900): QuoteLine
{
    return QuoteLine::query()->where('old_id', $oldId)->sole();
}

function realignQuoteUnderTest(): Quote
{
    return Quote::query()->where('old_id', 42)->sole();
}

beforeEach(function () {
    seedMigrationsConfig();
    Opportunity::factory()->create(['old_id' => 7]);
    legacyQuoteServiceProduct(10);
    Activity::query()->delete();
});

it('AC-013: a line changed since the date is updated and the offer totals follow', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);

    $report = realignQuoteLines(['lines' => [realignOfferLine(['unit_price' => 60, 'quantity' => 3])]]);

    expect((float) realignedLine()->net_amount)->toBe(180.0)
        ->and((float) realignQuoteUnderTest()->revenue_net)->toBe(180.0)
        ->and(collect($report->changes)->pluck(4)->all())->toContain('line.900', 'revenue_net')
        ->and(array_keys($report->amountChanges))->toBe([realignQuoteUnderTest()->code]);
});

it('AC-013: a line older than the date keeps its price even when it differs', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);

    $report = realignQuoteLines(['lines' => [realignOfferLine(['unit_price' => 70, 'updated_at' => REALIGN_OLD])]]);

    expect((float) realignedLine()->unit_price)->toBe(50.0)
        ->and($report->counters['quotes'][LegacyRealignReport::UNCHANGED])->toBe(1);
});

it('AC-013: a line held by a commessa is updated too (only invoices freeze it)', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => realignQuoteUnderTest()->id]);
    DB::table('quote_line_work_order')->insert(['quote_line_id' => realignedLine()->id, 'work_order_id' => $workOrder->id, 'renewal_number' => 0]);

    realignQuoteLines(['lines' => [realignOfferLine(['unit_price' => 60])]]);

    expect((float) realignedLine()->unit_price)->toBe(60.0);
});

it('AC-014: a line new in the legacy is created with its legacy id', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);

    realignQuoteLines(['lines' => [realignOfferLine(), realignOfferLine(['id' => 901, 'quantity' => 1, 'unit_price' => 20])]]);

    expect((float) realignedLine(901)->net_amount)->toBe(20.0)
        ->and(realignedLine(901)->sort_order)->toBe(1)
        ->and((float) realignQuoteUnderTest()->revenue_net)->toBe(120.0);
});

it('AC-014: a line gone from the legacy stays and is reported', function () {
    runLegacyQuotesImport([legacyQuoteRecord(['lines' => [realignOfferLine(), realignOfferLine(['id' => 902])]])]);

    $report = realignQuoteLines([]);

    expect(realignedLine(902))->not->toBeNull()
        ->and($report->messages[0][3])->toBe('Line 902 is no longer on the offer in the legacy; kept.');
});

it('AC-014: an invoiced line is not updated and is reported', function () {
    runLegacyQuotesImport([legacyQuoteRecord()]);
    InvoiceLine::factory()->create(['quote_line_id' => realignedLine()->id]);

    $report = realignQuoteLines(['lines' => [realignOfferLine(['unit_price' => 60])]]);

    expect((float) realignedLine()->unit_price)->toBe(50.0)
        ->and($report->messages[0][3])->toBe('Line 900 is already invoiced; not updated.');
});

describe('cost lines', function () {
    beforeEach(function () {
        $this->costProduct = Product::factory()->costOnly()->create(['old_source' => 'expense_reports', 'old_id' => 5]);
    });

    it('AC-015: a single match is updated and costs flow into the totals', function () {
        runLegacyQuotesImport([legacyQuoteRecord(['cost_lines' => [realignOfferCostLine()]])]);

        realignQuoteLines(['cost_lines' => [realignOfferCostLine(['unit_price' => 25])]]);

        expect((float) realignQuoteUnderTest()->costLines()->sole()->unit_price)->toBe(25.0)
            ->and((float) realignQuoteUnderTest()->cost_net)->toBe(25.0);
    });

    it('AC-015: an absent cost is created on its revenue line', function () {
        runLegacyQuotesImport([legacyQuoteRecord()]);

        realignQuoteLines(['cost_lines' => [realignOfferCostLine()]]);

        $line = realignQuoteUnderTest()->costLines()->sole();

        expect($line->line_type)->toBe(QuoteLineType::Cost)
            ->and($line->product_id)->toBe($this->costProduct->id)
            ->and($line->offer_line_id)->toBe(realignedLine()->id);
    });

    it('AC-015: an ambiguous cost is only reported', function () {
        runLegacyQuotesImport([legacyQuoteRecord(['cost_lines' => [realignOfferCostLine(), realignOfferCostLine(['id' => 'quotationexpensereports:2', 'unit_price' => 20])]])]);

        $report = realignQuoteLines(['cost_lines' => [realignOfferCostLine(['id' => 'quotationexpensereports:3', 'unit_price' => 99])]]);

        expect(realignQuoteUnderTest()->costLines()->pluck('unit_price')->map(fn ($p) => (float) $p)->all())->toBe([10.0, 20.0])
            ->and($report->messages[0][3])->toContain('quotationexpensereports:3');
    });

    it('AC-013: an old cost keeps its price', function () {
        runLegacyQuotesImport([legacyQuoteRecord(['cost_lines' => [realignOfferCostLine()]])]);

        realignQuoteLines(['cost_lines' => [realignOfferCostLine(['unit_price' => 25, 'updated_at' => REALIGN_OLD])]]);

        expect((float) realignQuoteUnderTest()->costLines()->sole()->unit_price)->toBe(10.0);
    });
});

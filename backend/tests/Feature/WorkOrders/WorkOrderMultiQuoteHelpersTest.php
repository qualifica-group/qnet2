<?php

use App\Models\Opportunity;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function multiQuoteWorkOrderOf(Quote ...$quotes): WorkOrder
{
    $workOrder = WorkOrder::factory()->multiQuote()->create();
    foreach ($quotes as $quote) {
        $workOrder->quoteLines()->attach(QuoteLine::factory()->create(['quote_id' => $quote->id])->id);
    }

    return $workOrder;
}

it('isMultiQuote follows quote_id', function () {
    expect(WorkOrder::factory()->create()->isMultiQuote())->toBeFalse()
        ->and(WorkOrder::factory()->multiQuote()->create()->isMultiQuote())->toBeTrue();
});

it('sourceQuoteIds returns the distinct sorted offers of the lines', function () {
    [$a, $b] = [Quote::factory()->create(), Quote::factory()->create()];
    $workOrder = multiQuoteWorkOrderOf($b, $a, $b);

    expect($workOrder->sourceQuoteIds())->toBe([$a->id, $b->id]);
});

it('sourceQuoteIds merges the own quote of a mono-offer work order', function () {
    [$own, $other] = [Quote::factory()->create(), Quote::factory()->create()];
    $workOrder = WorkOrder::factory()->create(['quote_id' => $own->id]);
    $workOrder->quoteLines()->attach(QuoteLine::factory()->create(['quote_id' => $own->id])->id);
    $workOrder->quoteLines()->attach(QuoteLine::factory()->create(['quote_id' => $other->id])->id);

    expect($workOrder->sourceQuoteIds())->toBe(collect([$own->id, $other->id])->sort()->values()->all());
});

it('withQuoteLineOfQuote matches direct and line-derived offers only', function () {
    [$a, $b, $c] = [Quote::factory()->create(), Quote::factory()->create(), Quote::factory()->create()];
    $direct = WorkOrder::factory()->create(['quote_id' => $a->id]);
    $multi = multiQuoteWorkOrderOf($a, $b);
    $unrelated = multiQuoteWorkOrderOf($c);

    $ids = WorkOrder::query()->withQuoteLineOfQuote($a->id)->pluck('id')->all();

    expect($ids)->toContain($direct->id, $multi->id)->not->toContain($unrelated->id);
});

it('withQuoteLineOfRegistry matches direct and line-derived registries only', function () {
    $quoteA = Quote::factory()->create();
    $quoteB = Quote::factory()->create();
    $registryA = Opportunity::query()->find($quoteA->opportunity_id)->registry_id;
    $direct = WorkOrder::factory()->create(['quote_id' => $quoteA->id]);
    $multi = multiQuoteWorkOrderOf($quoteA, $quoteB);
    $other = multiQuoteWorkOrderOf($quoteB);

    $ids = WorkOrder::query()->withQuoteLineOfRegistry($registryA)->pluck('id')->all();

    expect($ids)->toContain($direct->id, $multi->id)->not->toContain($other->id);
});

it('ProformaRequest creating hook defaults quote_id from a mono-offer work order', function () {
    $workOrder = WorkOrder::factory()->create();

    expect(ProformaRequest::factory()->create(['work_order_id' => $workOrder->id])->quote_id)->toBe($workOrder->quote_id);
});

it('ProformaRequest creating hook throws for a multi-offer work order without quote_id', function () {
    $workOrder = WorkOrder::factory()->multiQuote()->create();

    expect(fn () => ProformaRequest::factory()->create(['work_order_id' => $workOrder->id]))
        ->toThrow(LogicException::class, 'quote_id is required');
});

it('ProformaRequest accepts an explicit quote_id for a multi-offer work order', function () {
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->multiQuote()->create();

    expect(ProformaRequest::factory()->create(['work_order_id' => $workOrder->id, 'quote_id' => $quote->id])->quote->is($quote))->toBeTrue();
});

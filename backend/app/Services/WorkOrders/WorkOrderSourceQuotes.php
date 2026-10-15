<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The offers a commessa draws from (spec 0267, D-4/D-7): its own `quote` when
 * mono-offer, the distinct offers of its pivot lines when multi-offer
 * (`quote_id` null). Both entry points are eager-load friendly: no query per
 * row under `Model::preventLazyLoading()`.
 */
final class WorkOrderSourceQuotes
{
    /** Relations the detail projection (`source_quotes`) reads off each offer. */
    private const array DETAIL_RELATIONS = ['contract', 'opportunity.registry', 'company'];

    /**
     * Offers with the detail relations loaded, ordered by code. Mono-offer
     * reuses the already loaded `quote`; multi-offer costs one bounded query.
     *
     * @return Collection<int, Quote>
     */
    public function forDetail(WorkOrder $workOrder): Collection
    {
        if (! $workOrder->isMultiQuote()) {
            $quote = $workOrder->loadMissing('quote')->quote;

            return new Collection($quote === null ? [] : [$quote->loadMissing(self::DETAIL_RELATIONS)]);
        }

        return Quote::query()
            ->whereIn('id', $workOrder->sourceQuoteIds())
            ->with(self::DETAIL_RELATIONS)
            ->orderBy('code')
            ->get();
    }

    /**
     * Distinct offers of the lines, read from `quoteLines.quote` already
     * eager-loaded by the caller (grid rows), ordered by code.
     *
     * @return Collection<int, Quote>
     */
    public function fromLoadedLines(WorkOrder $workOrder): Collection
    {
        return (new Collection($workOrder->quoteLines->map(static fn ($line): ?Quote => $line->quote)->filter()->all()))
            ->unique('id')
            ->sortBy('code')
            ->values();
    }
}

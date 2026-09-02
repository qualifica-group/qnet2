<?php

declare(strict_types=1);

namespace App\Services\QuoteOfferLines;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * GET /api/quote-offer-lines/for-select (spec 0093, D-8): the REVENUE lines
 * of ONE quote (`quote_id`, required — FormRequest-enforced), ordered by
 * `sort_order`, feeding the WorkOrder form's multi-select. COST lines are
 * never eligible (D-7): they are internal costs, not sold products.
 *
 * Spec 0095, D-7: a line already programmed into ANOTHER work order (D-4's
 * "una riga, una sola Commessa") is excluded, except the ones belonging to
 * `exceptWorkOrderId` — the work-order EDIT form's own picker, so it keeps
 * offering the lines that work order already owns.
 *
 * Its OWN class, not a method on QuoteService/QuoteLineCoverageWriter: a
 * for-select is a self-contained read with no overlap with either's write
 * lifecycle, mirroring QuoteForSelectService.
 */
final class QuoteOfferLineForSelectService
{
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        /** @var int $quoteId FormRequest guarantees presence (required). */
        $quoteId = (int) $query->quoteId;

        $base = $this->baseQuery($quoteId, $query->exceptWorkOrderId);

        if ($query->hasSearch()) {
            $base->whereHas('product', function (Builder $products) use ($query): void {
                $products->where('code', 'like', '%'.$query->search.'%')
                    ->orWhere('name', 'like', '%'.$query->search.'%');
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, QuoteLine> $page */
        $page = $base->orderBy('sort_order')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query, $quoteId),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @return Builder<QuoteLine>
     */
    private function baseQuery(int $quoteId, ?int $exceptWorkOrderId): Builder
    {
        return QuoteLine::query()
            ->with('product')
            ->where('quote_id', $quoteId)
            ->where('line_type', QuoteLineType::Revenue)
            ->where(function (Builder $availability) use ($exceptWorkOrderId): void {
                $availability->whereDoesntHave('workOrders');

                if ($exceptWorkOrderId !== null) {
                    $availability->orWhereHas(
                        'workOrders',
                        fn (Builder $workOrders) => $workOrders->where('work_orders.id', $exceptWorkOrderId),
                    );
                }
            });
    }

    /**
     * Edit-mode hydration (ADR 0011): the ids the client already holds are
     * appended deduplicated, bypassing the search filter and never inflating
     * `total`.
     *
     * @param  Collection<int, QuoteLine>  $page
     * @return Collection<int, QuoteLine>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query, int $quoteId): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, QuoteLine> $hydrated */
        $hydrated = $this->baseQuery($quoteId, $query->exceptWorkOrderId)
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}

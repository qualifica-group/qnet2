<?php

declare(strict_types=1);

namespace App\Services\QuoteOfferLines;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Services\WorkOrders\RenewalPeriodResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * GET /api/quote-offer-lines/for-select (spec 0093, D-8): the REVENUE lines
 * of ONE quote (`quote_id`, required — FormRequest-enforced), ordered by
 * `sort_order`, feeding the WorkOrder form's multi-select. COST lines are
 * never eligible (D-7): they are internal costs, not sold products.
 *
 * Spec 0095, D-7 / spec 0358 D-5: a line already programmed into ANOTHER work
 * order IN THE SAME renewal period is excluded, except the ones belonging to
 * `exceptWorkOrderId` — the work-order EDIT form's own picker, so it keeps
 * offering the lines that work order already owns.
 *
 * Its OWN class, not a method on QuoteService/QuoteLineCoverageWriter: a
 * for-select is a self-contained read with no overlap with either's write
 * lifecycle, mirroring QuoteForSelectService.
 */
final class QuoteOfferLineForSelectService
{
    public function __construct(private readonly RenewalPeriodResolver $periods) {}

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

        $window = $query->page($base, static fn ($ordered) => $ordered->orderBy('sort_order')->orderBy('id'));

        /** @var Collection<int, QuoteLine> $page */
        $page = $window->items;

        return $window->withItems($this->appendHydratedIds($page, $query, $quoteId));
    }

    /**
     * @return Builder<QuoteLine>
     */
    private function baseQuery(int $quoteId, ?int $exceptWorkOrderId): Builder
    {
        $period = $this->periodFor($quoteId, $exceptWorkOrderId);

        return QuoteLine::query()
            ->with('product')
            ->where('quote_id', $quoteId)
            ->where('line_type', QuoteLineType::Revenue)
            ->where(function (Builder $availability) use ($exceptWorkOrderId, $period): void {
                $availability->whereDoesntHave(
                    'workOrders',
                    fn (Builder $workOrders) => $workOrders->where('quote_line_work_order.renewal_number', $period),
                );

                if ($exceptWorkOrderId !== null) {
                    $availability->orWhereHas(
                        'workOrders',
                        fn (Builder $workOrders) => $workOrders->where('work_orders.id', $exceptWorkOrderId),
                    );
                }
            });
    }

    /**
     * Spec 0358, D-4: the period of the commessa being edited for this offer,
     * otherwise the contract's current one.
     */
    private function periodFor(int $quoteId, ?int $exceptWorkOrderId): int
    {
        $workOrder = $exceptWorkOrderId === null ? new WorkOrder : WorkOrder::query()->find($exceptWorkOrderId);

        return $this->periods->periodForWorkOrderLines($workOrder ?? new WorkOrder, [$quoteId])[$quoteId] ?? 0;
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

<?php

namespace Database\Seeders\Support;

use App\Enums\ProductUsage;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use App\Services\ProductCategories\CategoryHierarchy;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The "at least one row per category" guarantee of the sample chain (user
 * directive 2026-09-29): which SELLABLE categories still lack a row at each
 * level of the chain Opportunita' -> Offerta -> Contratto -> Commessa, so
 * every step can cover them first and then append its ordinary batch.
 *
 * A category is SELLABLE when an opportunity can stand on it and an offer can
 * be priced on it: selectable (spec 0074), with an effective business function
 * (spec 0044), and with at least one product of its OWN usable as SALE
 * (spec 0142). A category without one is out of reach by construction, never
 * padded with a fabricated product.
 *
 * A category's DEEPEST level is the Commessa when its branch is sold under a
 * contract, the Offerta otherwise: a `generates_contract = false` branch
 * (spec 0091, "Formazione") never opens a contract, so it can never be
 * programmed either.
 *
 * Coverage is read from the categories an opportunity CARRIES (its product
 * lines), the same source ContractEligibility reads: a Commessa covers every
 * category of the deal it was programmed from.
 */
final class SampleCategoryCoverage
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * Sellable categories not yet carried down to their deepest level: the
     * ones the opportunity step opens a deal for.
     *
     * @return list<int>
     */
    public function incompleteCategoryIds(): array
    {
        $sellable = $this->sellableCategoryIds();
        $contractIds = $this->contractCategoryIds($sellable);

        return array_values(array_unique([
            ...array_diff($contractIds, $this->programmedCategoryIds()),
            ...array_diff(array_diff($sellable, $contractIds), $this->categoryIdsCarriedBy(
                static fn (Builder $query) => $query->has('quotes'),
            )),
        ]));
    }

    /**
     * @return list<int>
     */
    public function unquotedCategoryIds(): array
    {
        return array_values(array_diff($this->sellableCategoryIds(), $this->categoryIdsCarriedBy(
            static fn (Builder $query) => $query->has('quotes'),
        )));
    }

    /**
     * @return list<int>
     */
    public function uncontractedCategoryIds(): array
    {
        return array_values(array_diff($this->contractCategoryIds($this->sellableCategoryIds()), $this->categoryIdsCarriedBy(
            static fn (Builder $query) => $query->has('quotes.contract'),
        )));
    }

    /**
     * @return list<int>
     */
    public function unprogrammedCategoryIds(): array
    {
        return array_values(array_diff($this->contractCategoryIds($this->sellableCategoryIds()), $this->programmedCategoryIds()));
    }

    /**
     * The categories carried by the opportunities created after
     * $sinceOpportunityId: the running batch already has a deal on them.
     *
     * @return list<int>
     */
    public function categoryIdsCarriedSince(int $sinceOpportunityId): array
    {
        return $this->categoryIdsCarriedBy(
            static fn (Builder $query) => $query->where('id', '>', $sinceOpportunityId),
        );
    }

    /**
     * The first candidate carrying each of $categoryIds, in candidate order;
     * one candidate may cover several categories at once.
     *
     * @template TCandidate
     *
     * @param  Collection<int, TCandidate>  $candidates
     * @param  list<int>  $categoryIds
     * @param  Closure(TCandidate): list<int>  $categoryIdsOf
     * @return Collection<int, TCandidate>
     */
    public function pickCovering(Collection $candidates, array $categoryIds, Closure $categoryIdsOf): Collection
    {
        $pending = array_fill_keys($categoryIds, true);
        $picked = [];

        foreach ($candidates as $candidate) {
            if ($pending === []) {
                break;
            }

            $hits = array_intersect_key($pending, array_flip($categoryIdsOf($candidate)));

            if ($hits !== []) {
                $picked[] = $candidate;
                $pending = array_diff_key($pending, $hits);
            }
        }

        return $candidates->make($picked);
    }

    /**
     * @return list<int>
     */
    private function sellableCategoryIds(): array
    {
        $withSaleProduct = Product::query()
            ->whereJsonContains('usages', ProductUsage::Sale->value)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->all();

        $summaries = $this->hierarchy->effectiveBusinessFunctionSummaries();

        return ProductCategory::query()
            ->where('is_selectable', true)
            ->whereIn('id', $withSaleProduct)
            ->orderBy('id')
            ->pluck('id')
            ->filter(static fn (int $id): bool => ($summaries[$id] ?? null) !== null)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function contractCategoryIds(array $categoryIds): array
    {
        return ProductCategory::query()
            ->whereIn('id', $categoryIds)
            ->where('generates_contract', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @return list<int>
     */
    private function programmedCategoryIds(): array
    {
        return $this->categoryIdsCarriedBy(
            static fn (Builder $query) => $query->whereHas(
                'quotes',
                static fn (Builder $quotes) => $quotes->whereIn('id', WorkOrder::query()->whereNotNull('quote_id')->select('quote_id')),
            ),
        );
    }

    /**
     * @param  Closure(Builder): mixed  $opportunityConstraint
     * @return list<int>
     */
    private function categoryIdsCarriedBy(Closure $opportunityConstraint): array
    {
        return OpportunityProductLine::query()
            ->whereHas('opportunity', $opportunityConstraint)
            ->distinct()
            ->pluck('product_category_id')
            ->all();
    }
}

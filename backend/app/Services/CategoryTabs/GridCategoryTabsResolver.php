<?php

declare(strict_types=1);

namespace App\Services\CategoryTabs;

use App\Enums\QuoteLineType;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\Contracts\ContractVisibilityScope;
use App\Services\Opportunities\OpportunityVisibilityScope;
use App\Services\Quotes\QuoteVisibilityScope;
use App\Support\Cache\AggregateCache;
use App\Tables\CategoryTabs\CategoryTabGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves the category tab strip of the Opportunities, Offerte and Contratti grids
 * (spec 0335): the product categories present in the rows the actor sees,
 * each with the count of DISTINCT rows (D-1, D-2).
 *
 * The visibility scope is the grid's own (`scopeToActor`), so the strip can
 * never drift from what the grid lists (D-6). Mirrors
 * `RequestCategoryTabsResolver`, which stays dedicated to Gestione Richieste.
 */
final class GridCategoryTabsResolver
{
    public function __construct(private readonly AggregateCache $cache) {}

    /**
     * @return Collection<int, ProductCategory>
     */
    public function resolve(User $user, CategoryTabGrid $grid): Collection
    {
        // Step 1: the per-category counts, cached per user and grid; always
        // exact for the writer (AggregateCache).
        $counts = $this->cache->remember(
            "category-tabs:{$grid->value}:{$user->id}",
            fn (): array => $this->countsInScope($user, $grid),
            $user,
        );

        // Step 2: only non-zero categories, ordered by name; names stay live.
        return ProductCategory::query()
            ->whereKey(array_keys($counts))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(static fn (ProductCategory $category) => $category->setAttribute('records_count', $counts[$category->id]));
    }

    /**
     * @return array<int, int> category id => distinct rows
     */
    private function countsInScope(User $user, CategoryTabGrid $grid): array
    {
        $query = match ($grid) {
            CategoryTabGrid::Opportunities => $this->opportunityCounts($user),
            CategoryTabGrid::Quotes => $this->quoteCounts($user),
            CategoryTabGrid::Contracts => $this->contractCounts($user),
        };

        return $query
            ->pluck('records_count', 'category_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return Builder<Opportunity>
     */
    private function opportunityCounts(User $user): Builder
    {
        $query = Opportunity::query()
            ->join('opportunity_product_lines', 'opportunity_product_lines.opportunity_id', '=', 'opportunities.id')
            ->select('opportunity_product_lines.product_category_id as category_id')
            ->selectRaw('count(distinct opportunities.id) as records_count')
            ->groupBy('opportunity_product_lines.product_category_id');

        return OpportunityVisibilityScope::scopeToActor($query, $user);
    }

    /**
     * @return Builder<Quote>
     */
    private function quoteCounts(User $user): Builder
    {
        $query = Quote::query()
            ->join('quote_lines', 'quote_lines.quote_id', '=', 'quotes.id')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value)
            ->select('products.category_id as category_id')
            ->selectRaw('count(distinct quotes.id) as records_count')
            ->groupBy('products.category_id');

        return QuoteVisibilityScope::scopeToActor($query, $user);
    }

    /**
     * A contract takes the categories of the revenue lines of its own quote.
     *
     * @return Builder<Contract>
     */
    private function contractCounts(User $user): Builder
    {
        $query = Contract::query()
            ->join('quote_lines', 'quote_lines.quote_id', '=', 'contracts.quote_id')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value)
            ->select('products.category_id as category_id')
            ->selectRaw('count(distinct contracts.id) as records_count')
            ->groupBy('products.category_id');

        return ContractVisibilityScope::scopeToActor($query, $user);
    }
}

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves the category tab strip of the Opportunities, Offerte and Contratti grids
 * (spec 0335): the product categories present in the rows the actor sees,
 * each with the count of DISTINCT rows (D-1, D-2).
 *
 * The visibility scope is the grid's own (`scopeToActor`), so the strip can
 * never drift from what the grid lists (D-6). At the root level (spec 0337)
 * the strip lists the root categories, each counting its whole branch. Mirrors
 * `RequestCategoryTabsResolver`, which stays dedicated to Gestione Richieste.
 */
final class GridCategoryTabsResolver
{
    /**
     * Per grid: the category column the counts group on and the record id
     * they count. Closed constants, the only SQL identifiers interpolated below.
     */
    private const array COLUMNS = [
        'opportunities' => ['opportunity_product_lines.product_category_id', 'opportunities.id'],
        'quotes' => ['products.category_id', 'quotes.id'],
        'contracts' => ['products.category_id', 'contracts.id'],
    ];

    public function __construct(
        private readonly AggregateCache $cache,
        private readonly RootCategoryCounts $rootCounts,
    ) {}

    /**
     * @return Collection<int, ProductCategory>
     */
    public function resolve(User $user, CategoryTabGrid $grid, CategoryTabLevel $level = CategoryTabLevel::All): Collection
    {
        // Step 1: the per-category (or per-root) counts, cached per user, grid
        // and level; always exact for the writer (AggregateCache).
        $root = $level === CategoryTabLevel::Root;
        $counts = $this->cache->remember(
            "category-tabs:{$grid->value}:{$user->id}".($root ? ':root' : ''),
            fn (): array => $root ? $this->rootCountsInScope($user, $grid) : $this->countsInScope($user, $grid),
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
        [$categoryColumn, $recordColumn] = self::COLUMNS[$grid->value];

        return $this->scopedQuery($user, $grid)
            ->select("{$categoryColumn} as category_id")
            ->selectRaw("count(distinct {$recordColumn}) as records_count")
            ->groupBy($categoryColumn)
            ->pluck('records_count', 'category_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<int, int> root category id => distinct rows on its branch
     */
    private function rootCountsInScope(User $user, CategoryTabGrid $grid): array
    {
        [$categoryColumn, $recordColumn] = self::COLUMNS[$grid->value];

        return $this->rootCounts->count($this->scopedQuery($user, $grid), $categoryColumn, $recordColumn);
    }

    /**
     * The grid's rows joined to their categories, narrowed to what the actor sees.
     *
     * @return Builder<Model>
     */
    private function scopedQuery(User $user, CategoryTabGrid $grid): Builder
    {
        return match ($grid) {
            CategoryTabGrid::Opportunities => OpportunityVisibilityScope::scopeToActor($this->opportunityRows(), $user),
            CategoryTabGrid::Quotes => QuoteVisibilityScope::scopeToActor($this->quoteRows(), $user),
            CategoryTabGrid::Contracts => ContractVisibilityScope::scopeToActor($this->contractRows(), $user),
        };
    }

    /**
     * @return Builder<Opportunity>
     */
    private function opportunityRows(): Builder
    {
        return Opportunity::query()
            ->join('opportunity_product_lines', 'opportunity_product_lines.opportunity_id', '=', 'opportunities.id');
    }

    /**
     * @return Builder<Quote>
     */
    private function quoteRows(): Builder
    {
        return Quote::query()
            ->join('quote_lines', 'quote_lines.quote_id', '=', 'quotes.id')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value);
    }

    /**
     * A contract takes the categories of the revenue lines of its own quote.
     *
     * @return Builder<Contract>
     */
    private function contractRows(): Builder
    {
        return Contract::query()
            ->join('quote_lines', 'quote_lines.quote_id', '=', 'contracts.quote_id')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value);
    }
}

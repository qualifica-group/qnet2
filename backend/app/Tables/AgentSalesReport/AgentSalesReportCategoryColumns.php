<?php

declare(strict_types=1);

namespace App\Tables\AgentSalesReport;

use App\Enums\QuoteLineType;
use App\Models\Quote;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategories\CategoryRootResolver;
use App\Support\QuoteWorkflows\QuoteClassificationSource;
use App\Tables\CategoryTabs\RevenueCategoryQuoteIds;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The two category columns of the `agent-sales-report` domain (tasks 427625 and 427940):
 * `root_category` ("Linea prodotto", the root of the product category tree: ISO, SOA,
 * Formazione...) and `category` ("Categoria prodotto"). Both read the offer's categories
 * with the classification rule of the workflows (QuoteClassificationSource): the categories
 * of the products on its REVENUE lines, or, only when it has none, the categories of its
 * Opportunita's product lines. Without the fallback the offers created from Gestione
 * Richieste or Opportunita' with no product yet (most ISO/SOA ones) would have no category.
 *
 * Set filter + distinct values, not sortable (an offer can carry several categories). The
 * row value is the comma-separated list of distinct names, ordered by name.
 *
 * SECURITY: filter values are matched in PHP against the category names, then bound as ids.
 */
final class AgentSalesReportCategoryColumns
{
    use HandlesBlankSetFilter;

    public const string ROOT_CATEGORY = 'root_category';

    public const string CATEGORY = 'category';

    /** Caps the WHERE IN cardinality of the set filter (defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

    private const string SEPARATOR = ', ';

    /** @var array<int, string>|null category id => name, memoized per request */
    private ?array $names = null;

    public function __construct(
        private readonly QuoteClassificationSource $classification,
        private readonly CategoryRootResolver $rootResolver,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    /**
     * Relations mapRow() reads; the caller eager-loads them on the page.
     *
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return ['offerLines.product', 'opportunity.productLines'];
    }

    /**
     * @return array<string, string|null>
     */
    public function project(Quote $quote): array
    {
        $categoryIds = $this->classification->categoryIds($quote);
        $roots = $categoryIds === [] ? [] : $this->rootResolver->rootSummariesFor($categoryIds);

        return [
            self::ROOT_CATEGORY => $this->join(array_map(static fn (?array $root): ?string => $root['name'] ?? null, $roots)),
            self::CATEGORY => $this->join(array_map(fn (int $id): ?string => $this->names()[$id] ?? null, $categoryIds)),
        ];
    }

    /**
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if (! in_array($columnId, [self::ROOT_CATEGORY, self::CATEGORY], true)) {
            return false;
        }

        $values = $this->filterValues($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return true;
        }

        $categoryIds = $columnId === self::ROOT_CATEGORY ? $this->idsUnderRoots($values) : $this->idsNamed($values);

        $query->where(function (Builder $group) use ($categoryIds, $values, $matchesBlank): void {
            if ($values !== []) {
                $group->where(fn (Builder $matching) => $this->whereClassifiedBy($matching, $categoryIds));
            }

            if ($matchesBlank) {
                $group->orWhere(fn (Builder $blank) => $this->whereUnclassified($blank));
            }
        });

        return true;
    }

    /**
     * Excel-like distinct values, scoped by every other active filter.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if (! in_array($columnId, [self::ROOT_CATEGORY, self::CATEGORY], true)) {
            return null;
        }

        $categoryIds = $this->categoryIdsOf($query);
        $names = $columnId === self::ROOT_CATEGORY
            ? array_map(static fn (?array $root): ?string => $root['name'] ?? null, $categoryIds === [] ? [] : $this->rootResolver->rootSummariesFor($categoryIds))
            : array_map(fn (int $id): ?string => $this->names()[$id] ?? null, $categoryIds);

        $values = collect($names)
            ->filter()
            ->unique()
            ->when($search !== null && $search !== '', fn ($items) => $items->filter(static fn (string $name): bool => mb_stripos($name, (string) $search) !== false))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->take($limit)
            ->values()
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => $this->whereUnclassified(clone $query)->exists());
    }

    /**
     * Same rule as QuoteClassificationSource, in SQL: a revenue line on one of the categories,
     * or no revenue line at all and an Opportunita' product line on one of them.
     *
     * @param  Builder<Quote>  $query
     * @param  array<int, int>  $categoryIds
     */
    private function whereClassifiedBy(Builder $query, array $categoryIds): void
    {
        $query->whereIn('quotes.id', RevenueCategoryQuoteIds::subquery($categoryIds))
            ->orWhere(function (Builder $fallback) use ($categoryIds): void {
                $fallback->whereNotExists($this->revenueLines())
                    ->whereIn('quotes.opportunity_id', static fn (QueryBuilder $lines): QueryBuilder => $lines
                        ->select('opportunity_id')
                        ->from('opportunity_product_lines')
                        ->whereIn('product_category_id', $categoryIds));
            });
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private function whereUnclassified(Builder $query): Builder
    {
        return $query->whereNotExists($this->revenueLines())
            ->whereNotExists(static fn (QueryBuilder $lines): QueryBuilder => $lines
                ->select(DB::raw(1))
                ->from('opportunity_product_lines')
                ->whereColumn('opportunity_product_lines.opportunity_id', 'quotes.opportunity_id'));
    }

    /**
     * @return Closure(QueryBuilder): QueryBuilder
     */
    private function revenueLines(): Closure
    {
        return static fn (QueryBuilder $lines): QueryBuilder => $lines
            ->select(DB::raw(1))
            ->from('quote_lines')
            ->whereColumn('quote_lines.quote_id', 'quotes.id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value);
    }

    /**
     * The distinct categories of the offers matching $query, with the same fallback.
     *
     * @param  Builder<Model>  $query
     * @return array<int, int>
     */
    private function categoryIdsOf(Builder $query): array
    {
        $quoteIds = (clone $query)->select('quotes.id');

        $fromLines = DB::table('quote_lines')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value)
            ->whereIn('quote_lines.quote_id', $quoteIds)
            ->distinct()
            ->pluck('products.category_id');

        $fromOpportunities = DB::table('opportunity_product_lines')
            ->whereIn('opportunity_id', (clone $query)->whereNotExists($this->revenueLines())->select('quotes.opportunity_id'))
            ->distinct()
            ->pluck('product_category_id');

        return $fromLines->merge($fromOpportunities)->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    /**
     * Every category id (roots included) whose root is named in $names.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function idsUnderRoots(array $names): array
    {
        $allIds = array_keys($this->hierarchy->parentIdMap());

        return collect($this->rootResolver->rootSummariesFor($allIds))
            ->filter(static fn (?array $root): bool => $root !== null && in_array($root['name'], $names, true))
            ->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function idsNamed(array $names): array
    {
        return array_keys(array_filter($this->names(), static fn (string $name): bool => in_array($name, $names, true)));
    }

    /**
     * @return array<int, string>
     */
    private function names(): array
    {
        return $this->names ??= DB::table('product_categories')
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * @param  array<int|string, string|null>  $names
     */
    private function join(array $names): ?string
    {
        $distinct = collect($names)->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $distinct->isEmpty() ? null : $distinct->implode(self::SEPARATOR);
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        return is_array($values) ? array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES) : [];
    }
}

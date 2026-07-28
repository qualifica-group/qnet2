<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * Data-access side of the category-attribute scope (spec 0064, §M2): resolves
 * the EFFECTIVE (own + inherited) Opportunity-context attribute rows for one
 * product category, or the UNION (deduped by `code`) across every category —
 * each memoized per instance (one `AttributeScopedTableDefinition` per
 * request, see TableRegistry::resolve()) so repeated calls within one
 * request never re-walk the hierarchy.
 *
 * A nonexistent category id resolves to an EMPTY collection rather than
 * throwing: existence is a 422 the caller's own FormRequest validates
 * separately (Rule::exists) — this class only ever answers "what attributes
 * apply", never enforces authorization/existence.
 */
final class AttributeScopeResolver
{
    /** @var array<int, Collection<int, array<string, mixed>>> */
    private array $byCategory = [];

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $union = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forCategory(?int $categoryId): Collection
    {
        if ($categoryId === null) {
            return collect();
        }

        return $this->byCategory[$categoryId] ??= $this->resolveForCategory($categoryId);
    }

    /**
     * The UNION, deduped by `code`, of every product category's effective
     * Opportunity attributes (spec 0064, D-4: the preferences/filters
     * allow-list must never 422 regardless of which tab produced the save).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function union(): Collection
    {
        return $this->union ??= $this->resolveUnion();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveForCategory(int $categoryId): Collection
    {
        $category = ProductCategory::find($categoryId);

        if ($category === null) {
            return collect();
        }

        // `CategoryHierarchy::effectiveAttributes()` orders root-first for
        // ITS OWN inheritance contract; spec 0064 (AC-005) requires a
        // strict [sort_order, code] order for the columns endpoint,
        // regardless of which level an attribute is inherited from.
        return self::sortBySortOrderThenCode($this->hierarchy->effectiveAttributes($category, AttributeContext::Opportunity));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveUnion(): Collection
    {
        $merged = [];

        foreach (ProductCategory::query()->pluck('id') as $categoryId) {
            foreach ($this->forCategory((int) $categoryId) as $row) {
                $merged[$row['code']] ??= $row;
            }
        }

        return self::sortBySortOrderThenCode(collect(array_values($merged)));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return Collection<int, array<string, mixed>>
     */
    private static function sortBySortOrderThenCode(Collection $attributes): Collection
    {
        $rows = $attributes->values()->all();

        usort(
            $rows,
            static fn (array $a, array $b): int => [$a['sort_order'], $a['code']] <=> [$b['sort_order'], $b['code']],
        );

        return collect($rows);
    }
}

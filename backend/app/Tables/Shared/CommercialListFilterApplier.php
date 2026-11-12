<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Enums\CategoryUsageArea;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Category filters of the Opportunità, Offerte and Contratti pages. Which
 * records the actor sees is the visibility scope's alone (user directive
 * 2026-11-12, no "Assegnazione" filter); how a record reaches its categories
 * is each module's own rule.
 */
final class CommercialListFilterApplier
{
    public function __construct(private readonly PresetListFilters $presets) {}

    /**
     * @param  CategoryUsageArea|null  $area  spec 0389 rev.2: the module whose roots the category filter lists
     * @return array<int, array<string, mixed>>
     */
    public function presetFilters(string $labelNamespace, ?User $actor, ?CategoryUsageArea $area = null): array
    {
        return [
            $this->presets->presetParentCategory(PresetListFilters::parentCategoryFilter($labelNamespace, 0, $area), $actor),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>, array<int, int>): void  $applyCategories
     * @return bool false when $name is not one of these filters
     */
    public function apply(Builder $query, string $name, mixed $value, ?User $actor, Closure $applyCategories): bool
    {
        if ($name !== PresetListFilters::PARENT_CATEGORY && $name !== PresetListFilters::PRODUCT_CATEGORY) {
            return false;
        }

        $categoryIds = $name === PresetListFilters::PARENT_CATEGORY ? $this->presets->branchIds($value, $actor) : PresetListFilters::ids($value);

        if ($categoryIds !== null && $categoryIds !== []) {
            $applyCategories($query, $categoryIds);
        }

        return true;
    }
}

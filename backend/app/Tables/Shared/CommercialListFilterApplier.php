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
 *
 * A module that passes its team predicate keeps, under the default parent
 * category of a limited actor, the records where the actor is in the team
 * whatever their category (user directive 2026-11-20, as on Commesse): a
 * pick among the actor's roots narrows to those alone.
 */
final class CommercialListFilterApplier
{
    public function __construct(private readonly PresetListFilters $presets) {}

    /**
     * @param  CategoryUsageArea|null  $area  spec 0389 rev.2: the module whose roots the category filter lists
     * @return array<int, array<string, mixed>>
     */
    public function presetFilters(string $labelNamespace, ParentCategoryListModule $module, ?CategoryUsageArea $area = null): array
    {
        return [
            PresetListFilters::presetParentCategory(PresetListFilters::parentCategoryFilter($labelNamespace, 0, $module, $area)),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>, array<int, int>): void  $applyCategories
     * @param  (Closure(Builder<Model>, User): mixed)|null  $applyTeam  the module's "actor is in the team" predicate
     * @return bool false when $name is not one of these filters
     */
    public function apply(Builder $query, string $name, mixed $value, ?User $actor, ParentCategoryListModule $module, Closure $applyCategories, ?Closure $applyTeam = null): bool
    {
        if ($name !== PresetListFilters::PARENT_CATEGORY && $name !== PresetListFilters::PRODUCT_CATEGORY) {
            return false;
        }

        $categoryIds = $name === PresetListFilters::PARENT_CATEGORY ? $this->presets->branchIds($value, $actor, $module) : PresetListFilters::ids($value);

        if ($categoryIds === null || $categoryIds === []) {
            return true;
        }

        if ($name === PresetListFilters::PARENT_CATEGORY && $applyTeam !== null && $actor !== null && $this->presets->isActorDefault($value, $actor, $module)) {
            $query->where(function (Builder $default) use ($applyCategories, $applyTeam, $categoryIds, $actor): void {
                $applyCategories($default, $categoryIds);
                $default->orWhere(fn (Builder $team) => $applyTeam($team, $actor));
            });

            return true;
        }

        $applyCategories($query, $categoryIds);

        return true;
    }
}

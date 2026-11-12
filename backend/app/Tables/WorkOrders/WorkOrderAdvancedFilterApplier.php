<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Models\User;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use App\Tables\Shared\PresetListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The derived advanced filters of the `work-orders` domain: a commessa has no
 * category or client of its own, it reaches both through its source offers
 * (WorkOrderSourceQuotes, the same rule as WorkOrderVisibilityScope).
 */
final class WorkOrderAdvancedFilterApplier
{
    public function __construct(private readonly PresetListFilters $presets) {}

    /**
     * The filters of the Commesse page: the domain's catalogue with the parent
     * category preset. Which commesse the actor sees is WorkOrderVisibilityScope's
     * alone (user directive 2026-11-12, no "Assegnazione" filter).
     *
     * @return array<int, array<string, mixed>>
     */
    public function commessePageFilters(): array
    {
        return array_map(
            static fn (array $filter): array => $filter['name'] === PresetListFilters::PARENT_CATEGORY ? PresetListFilters::presetParentCategory($filter) : $filter,
            WorkOrderAdvancedFilterCatalog::advancedFilters(),
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return bool false when $name is not one of these filters
     */
    public function apply(Builder $query, string $name, mixed $value, ?User $actor): bool
    {
        if (! in_array($name, WorkOrderAdvancedFilterCatalog::DERIVED_FILTERS, true)) {
            return false;
        }

        match ($name) {
            PresetListFilters::PARENT_CATEGORY => $this->applyRevenueCategories($query, $this->presets->branchIds($value, $actor) ?? []),
            PresetListFilters::PRODUCT_CATEGORY => $this->applyRevenueCategories($query, PresetListFilters::ids($value)),
            WorkOrderAdvancedFilterCatalog::REGISTRY => $this->applyRegistries($query, PresetListFilters::ids($value)),
        };

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, int>  $categoryIds
     */
    private function applyRevenueCategories(Builder $query, array $categoryIds): void
    {
        if ($categoryIds !== []) {
            $query->whereIn('work_orders.id', WorkOrderSourceQuotes::withRevenueCategory($categoryIds));
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, int>  $registryIds
     */
    private function applyRegistries(Builder $query, array $registryIds): void
    {
        if ($registryIds !== []) {
            $query->withQuoteLineOfRegistry($registryIds);
        }
    }
}

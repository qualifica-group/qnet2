<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\AdvancedFilterType;
use App\Tables\Shared\PresetListFilters;

/**
 * Advanced-filter catalogue for the `work-orders` domain.
 *
 * The Responsabili and the Partecipanti are person pickers, like
 * `assignees`/`watchers` on the Task grid: each `target` is the WorkOrder
 * relation of the same name, matched by id by the generic AdvancedFilterApplier.
 * The category and Anagrafica filters have no single relation behind them
 * (a commessa reaches them through its source offers) and are applied by
 * WorkOrderAdvancedFilterApplier.
 *
 * The Commesse page presets the parent category (WorkOrderAdvancedFilterApplier).
 */
final class WorkOrderAdvancedFilterCatalog
{
    public const string REGISTRY = 'registry';

    /** Applied by WorkOrderAdvancedFilterApplier; every other filter by the generic engine. */
    public const array DERIVED_FILTERS = [PresetListFilters::PARENT_CATEGORY, PresetListFilters::PRODUCT_CATEGORY, self::REGISTRY];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            self::teamFilter('supervisors', 3),
            self::teamFilter('participants', 4),
            PresetListFilters::parentCategoryFilter('workOrders', 5),
            PresetListFilters::productCategoryFilter('workOrders', 6),
            self::lookupFilter(self::REGISTRY, 'registry', 7, ['resource' => 'registries']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function teamFilter(string $relation, int $order): array
    {
        return self::lookupFilter($relation, $relation, $order, ['resource' => 'users']);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private static function lookupFilter(string $name, string $labelKey, int $order, array $source): array
    {
        return [
            'name' => $name,
            'label' => "workOrders.advancedFilters.{$labelKey}",
            'type' => AdvancedFilterType::Relation,
            'order' => $order,
            'required' => false,
            'visible' => true,
            'width' => 'md',
            'multiple' => true,
            'source' => $source,
            'target' => $name,
        ];
    }
}

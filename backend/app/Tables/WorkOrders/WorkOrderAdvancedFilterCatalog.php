<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\AdvancedFilterType;
use App\Enums\WorkOrderAssignmentScope;

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
 * The "personal view" filters open the Commesse page on the actor's own work
 * (user directive 2026-11-09). They are `required` with a `defaultValue` like
 * the Task `assignment`: the frontend omits a value equal to its default and
 * the engine then falls back to it, so the page never opens unscoped.
 */
final class WorkOrderAdvancedFilterCatalog
{
    public const string ASSIGNMENT = 'assignment';

    public const string PARENT_CATEGORY = 'parent_category';

    public const string PRODUCT_CATEGORY = 'product_category';

    public const string REGISTRY = 'registry';

    public const array PERSONAL_VIEW_FILTERS = [self::ASSIGNMENT, self::PARENT_CATEGORY];

    /** Applied by WorkOrderAdvancedFilterApplier; every other filter by the generic engine. */
    public const array DERIVED_FILTERS = [...self::PERSONAL_VIEW_FILTERS, self::PRODUCT_CATEGORY, self::REGISTRY];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            self::teamFilter('supervisors', 3),
            self::teamFilter('participants', 4),
            self::lookupFilter(self::PARENT_CATEGORY, 'parentCategory', 5, ['resource' => 'product-category-roots']),
            self::lookupFilter(self::PRODUCT_CATEGORY, 'productCategory', 6, ['resource' => 'product-categories', 'params' => ['include_inactive' => 1]]),
            self::lookupFilter(self::REGISTRY, 'registry', 7, ['resource' => 'registries']),
        ];
    }

    /**
     * The filters of the Commesse page: the catalogue above plus the personal
     * view, which only that page's grid honours
     * (QuoteScopedTableDefinition::applyAdvancedFilter).
     *
     * @param  array<int, int>  $defaultParentCategories
     * @return array<int, array<string, mixed>>
     */
    public static function commessePageFilters(array $defaultParentCategories): array
    {
        $filters = array_map(
            static fn (array $filter): array => $filter['name'] === self::PARENT_CATEGORY
                ? [...$filter, 'required' => true, 'defaultValue' => $defaultParentCategories]
                : $filter,
            self::advancedFilters(),
        );

        return [self::assignmentFilter(), ...$filters];
    }

    /**
     * @return array<string, mixed>
     */
    private static function teamFilter(string $relation, int $order): array
    {
        return [
            ...self::lookupFilter($relation, $relation, $order, ['resource' => 'users']),
            'target' => $relation,
        ];
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

    /**
     * @return array<string, mixed>
     */
    private static function assignmentFilter(): array
    {
        return [
            'name' => self::ASSIGNMENT,
            'label' => 'workOrders.advancedFilters.assignment',
            'type' => AdvancedFilterType::Enum,
            'order' => 1,
            'required' => true,
            'visible' => true,
            'width' => 'md',
            'multiple' => true,
            'target' => self::ASSIGNMENT,
            'enumKey' => 'work_order_assignment_scope',
            'defaultValue' => [WorkOrderAssignmentScope::Supervisor->value, WorkOrderAssignmentScope::Participant->value],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\AdvancedFilterType;
use App\Tables\Shared\ParentCategoryListModule;
use App\Tables\Shared\PresetListFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
 * The Commesse page has its own set, the filters of the legacy QNet page
 * (user request 2026-11-27): title, Anagrafica, month, parent category (preset
 * by WorkOrderAdvancedFilterApplier) and the users who are Responsabile or
 * Partecipante. The Progetti grid keeps advancedFilters().
 */
final class WorkOrderAdvancedFilterCatalog
{
    public const string REGISTRY = 'registry';

    /** Month of the start date in the current year, as the legacy "Mese" filter. */
    public const string MONTH = 'month';

    /** Commesse where any picked user is Responsabile or Partecipante. */
    public const string TEAM_MEMBERS = 'team_members';

    private const string TITLE = 'title';

    public const int MONTHS_IN_YEAR = 12;

    /** Applied by WorkOrderAdvancedFilterApplier; every other filter by the generic engine. */
    public const array DERIVED_FILTERS = [PresetListFilters::PARENT_CATEGORY, PresetListFilters::PRODUCT_CATEGORY, self::REGISTRY, self::MONTH, self::TEAM_MEMBERS];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            self::teamFilter('supervisors', 3),
            self::teamFilter('participants', 4),
            PresetListFilters::parentCategoryFilter('workOrders', 5, ParentCategoryListModule::WorkOrders),
            PresetListFilters::productCategoryFilter('workOrders', 6),
            self::lookupFilter(self::REGISTRY, 'registry', 7, ['resource' => 'registries']),
        ];
    }

    /**
     * The filters of the Commesse page, in the legacy order; the parent
     * category still without its preset (WorkOrderAdvancedFilterApplier).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function commessePageFilters(): array
    {
        return [
            [
                'name' => self::TITLE,
                'label' => 'workOrders.advancedFilters.title',
                'placeholder' => 'workOrders.advancedFilters.titlePlaceholder',
                'type' => AdvancedFilterType::Text,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'work_orders.title',
            ],
            [...self::lookupFilter(self::REGISTRY, 'registry', 2, ['resource' => 'registries']), 'placeholder' => 'workOrders.advancedFilters.registryPlaceholder'],
            [
                'name' => self::MONTH,
                'label' => 'workOrders.advancedFilters.month',
                'placeholder' => 'workOrders.advancedFilters.monthPlaceholder',
                'type' => AdvancedFilterType::Select,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'options' => self::monthOptions(),
            ],
            PresetListFilters::parentCategoryFilter('workOrders', 4, ParentCategoryListModule::WorkOrders),
            [...self::lookupFilter(self::TEAM_MEMBERS, 'teamMembers', 5, ['resource' => 'users']), 'placeholder' => 'workOrders.advancedFilters.teamMembersPlaceholder'],
        ];
    }

    /**
     * January to December, named in the actor's language.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private static function monthOptions(): array
    {
        return array_map(
            static fn (int $month): array => [
                'value' => $month,
                'label' => Str::ucfirst(Carbon::create(null, $month, 1)->locale(app()->getLocale())->translatedFormat('F')),
            ],
            range(1, self::MONTHS_IN_YEAR),
        );
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

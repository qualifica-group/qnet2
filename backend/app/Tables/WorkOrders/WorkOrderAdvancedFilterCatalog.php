<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `work-orders` domain: the Responsabili and
 * the Partecipanti as person pickers, like `assignees`/`watchers` on the Task
 * grid. Each `target` is the WorkOrder relation of the same name, matched by
 * id by the generic AdvancedFilterApplier.
 */
final class WorkOrderAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            self::teamFilter('supervisors', 1),
            self::teamFilter('participants', 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function teamFilter(string $relation, int $order): array
    {
        return [
            'name' => $relation,
            'label' => "workOrders.advancedFilters.{$relation}",
            'type' => AdvancedFilterType::Relation,
            'order' => $order,
            'required' => false,
            'visible' => true,
            'width' => 'md',
            'multiple' => true,
            'source' => ['resource' => 'users'],
            'target' => $relation,
        ];
    }
}

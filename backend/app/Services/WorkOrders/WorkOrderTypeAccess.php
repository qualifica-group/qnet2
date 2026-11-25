<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * The two menu modules a commessa belongs to (user decision 2026-11-25):
 * "Commesse" holds the `processing` work orders, "Commesse di Progetto" the
 * `project` ones. Same table, detail and actions, but each module has its
 * own VIEWING permissions (`work-orders.view|viewAny` and
 * `project-work-orders.view|viewAny`) and its own page links
 * (`/work-orders/{id}`, `/project-work-orders/{id}`). THE single place that
 * maps a work order type to them.
 *
 * Only viewing is split: every action on the record (update, chat, costs,
 * documents...) keeps its `work-orders.*` permission.
 */
final class WorkOrderTypeAccess
{
    public const string PROCESSING_RESOURCE = 'work-orders';

    public const string PROJECT_RESOURCE = 'project-work-orders';

    /** The permission prefix (and SPA path segment) of the module holding `$type`; no type reads as processing. */
    public static function resource(?WorkOrderType $type): string
    {
        return $type === WorkOrderType::Project ? self::PROJECT_RESOURCE : self::PROCESSING_RESOURCE;
    }

    /** The permission that opens a single commessa of `$type`. */
    public static function viewPermission(?WorkOrderType $type): string
    {
        return self::resource($type).'.view';
    }

    /** The permission that lists the commesse of `$type`. */
    public static function viewAnyPermission(WorkOrderType $type): string
    {
        return self::resource($type).'.viewAny';
    }

    /** The SPA page of the commessa: `/work-orders/{id}` or `/project-work-orders/{id}`. */
    public static function recordPath(WorkOrder $workOrder): string
    {
        return self::pathFor($workOrder->type, (int) $workOrder->getKey());
    }

    public static function pathFor(?WorkOrderType $type, int $workOrderId): string
    {
        return '/'.self::resource($type).'/'.$workOrderId;
    }

    /**
     * A project commessa is out of reach, whatever the ability asked, for
     * whoever may not open the "Commesse di Progetto" module. A processing one
     * keeps the rules it always had.
     */
    public static function isHiddenFrom(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->type === WorkOrderType::Project && ! $user->can(self::viewPermission(WorkOrderType::Project));
    }

    /**
     * Narrows a work orders list to the types whose module the actor may list:
     * no restriction when they may list both, nothing when they may list none
     * (fail closed, a null actor included).
     *
     * @template TModel of WorkOrder
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToListableTypes(Builder $query, ?User $user): Builder
    {
        $types = array_values(array_filter(
            WorkOrderType::cases(),
            static fn (WorkOrderType $type): bool => $user?->can(self::viewAnyPermission($type)) === true,
        ));

        if (count($types) === count(WorkOrderType::cases())) {
            return $query;
        }

        return $query->whereIn('work_orders.type', array_map(static fn (WorkOrderType $type): string => $type->value, $types));
    }
}

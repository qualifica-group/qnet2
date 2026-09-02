<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The SINGLE point of computation for a WorkOrder's working status (spec
 * 0093, D-3): `is_force_closed = true` -> `closed`, otherwise `open`. Owns
 * BOTH the badge value (resolve()) and the table's `set` filter
 * (applyFilter()) on the SAME class, so the two can never disagree (mirrors
 * spec 0082's OpportunityStatusResolver/OpportunityStatusScope invariant).
 *
 * The real business rule (progress of the underlying lavorazioni/progetti)
 * is not yet defined (D-3): this is the extension point. `EAGER_LOADS` is
 * declared empty today — a future rule that reads relations off $workOrder
 * lists them here so WorkOrdersTableDefinition::baseQuery() can eager-load
 * them without a second edit site.
 */
final class WorkOrderStatusResolver
{
    /**
     * Relations a future status rule would need eager-loaded. Empty today
     * (D-3): `is_force_closed` is a plain column already on the row.
     *
     * @var array<int, string>
     */
    public const array EAGER_LOADS = [];

    public function resolve(WorkOrder $workOrder): WorkOrderStatus
    {
        return $workOrder->is_force_closed ? WorkOrderStatus::Closed : WorkOrderStatus::Open;
    }

    /**
     * The `status` set filter (AC-034): matches EXACTLY the rows resolve()
     * would badge with the requested value(s) — both `open` and `closed`
     * requested together is a no-op (every row matches one or the other).
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, array $values): void
    {
        $wantsOpen = in_array(WorkOrderStatus::Open->value, $values, true);
        $wantsClosed = in_array(WorkOrderStatus::Closed->value, $values, true);

        if ($wantsOpen === $wantsClosed) {
            return; // neither requested, or both — no constraint either way.
        }

        $query->where('is_force_closed', $wantsClosed);
    }
}

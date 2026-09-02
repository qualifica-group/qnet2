<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single implementation of the "solo le mie commesse" rule (user
 * directive 2026-09-02): an actor sees a Commessa when they hold
 * `work-orders.viewAll` (or are super-admin, through the Gate::before
 * bypass) OR they are one of that commessa's Responsabili (`supervisors`)
 * or Partecipanti (`participants`).
 *
 * The rule NARROWS, it never widens: being a Responsabile does not grant
 * `work-orders.view` — the resource permission is still required, exactly as
 * `request-management.viewAll` narrows RequestManagementScope.
 *
 * Query shape and record shape are the SAME predicate expressed twice, so
 * the table's rows and the policy's per-record verdict can never disagree:
 * isVisibleTo() falls back to scopeToActor() whenever it cannot answer from
 * already-loaded relations.
 *
 * FAIL-CLOSED (non-negotiable): a null actor is scoped to a condition that
 * can never match a row, never left unrestricted.
 *
 * Static and stateless like RequestManagementScope::scopeToActor(), for the
 * same two reasons: TableDefinition::baseQuery() calls it inline with
 * `Auth::user()` and no DI wiring, and WorkOrderPolicy must stay
 * zero-argument constructible — `permissions:sync` discovers policies with
 * `new $class`.
 */
final class WorkOrderVisibilityScope
{
    public const VIEW_ALL_PERMISSION = 'work-orders.viewAll';

    /**
     * @template TModel of WorkOrder
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToActor(Builder $query, ?User $user): Builder
    {
        if ($user?->can(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        if ($user === null) {
            return $query->whereNull('work_orders.id');
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped
                ->whereHas('supervisors', fn (Builder $supervisors) => $supervisors->whereKey($user->id))
                ->orWhereHas('participants', fn (Builder $participants) => $participants->whereKey($user->id));
        });
    }

    /**
     * The same rule for one record. The in-memory branch is what keeps the
     * table affordable: WorkOrdersTableDefinition asks the Gate three times
     * per row, and both membership relations are eager-loaded there, so no
     * branch of that loop hits the database.
     */
    public static function isVisibleTo(User $user, WorkOrder $workOrder): bool
    {
        if ($user->can(self::VIEW_ALL_PERMISSION)) {
            return true;
        }

        if ($workOrder->relationLoaded('supervisors') && $workOrder->relationLoaded('participants')) {
            return $workOrder->supervisors->contains('id', $user->id)
                || $workOrder->participants->contains('id', $user->id);
        }

        return self::scopeToActor(WorkOrder::query()->whereKey($workOrder->getKey()), $user)->exists();
    }
}

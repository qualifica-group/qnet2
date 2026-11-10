<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderNotable;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * The commessa tier of TaskVisibilityScope (spec 0379): whoever sees a
 * Commessa (WorkOrderVisibilityScope, plus `work-orders.view` because that rule
 * only narrows) also sees its NON private Tasks. Read-only like the site and
 * team tiers: what may be done to the Task stays with TaskAbilityResolver.
 *
 * Static like the scope it serves, for the same reasons (TableDefinition and
 * TaskPolicy call it with no DI wiring).
 */
final class TaskWorkOrderTier
{
    /** @var WeakMap<User, array<int, bool>>|null */
    private static ?WeakMap $verdictsByActor = null;

    /** D-2: being a Responsabile without the permission to read commesse grants nothing. */
    public static function applies(User $user): bool
    {
        return $user->can(WorkOrderNotable::VIEW_PERMISSION);
    }

    /** D-7: the actor sees every commessa, so the tier is a plain predicate rather than an id list. */
    public static function seesEveryWorkOrder(User $user): bool
    {
        return self::applies($user) && WorkOrderVisibilityScope::seesEveryWorkOrder($user);
    }

    /**
     * The non private Tasks of the commesse the actor sees, shaped to join the
     * membership UNION (D-7): `tasks.work_order_id` is indexed, so the optimizer
     * still drives the outer query from these ids.
     */
    public static function visibleTaskIds(User $user): QueryBuilder
    {
        return DB::table('tasks')
            ->select('id')
            ->where('is_private', false)
            ->whereIn('work_order_id', WorkOrderVisibilityScope::scopeToActor(WorkOrder::query(), $user)->select('work_orders.id'));
    }

    /**
     * The same rule for one record. The verdict per commessa is memoized per
     * actor instance: a grid page asks once per row and many rows share a commessa.
     */
    public static function reaches(User $user, Task $task): bool
    {
        if ($task->is_private || $task->work_order_id === null || ! self::applies($user)) {
            return false;
        }

        if (WorkOrderVisibilityScope::seesEveryWorkOrder($user)) {
            return true;
        }

        self::$verdictsByActor ??= new WeakMap;
        $verdicts = self::$verdictsByActor[$user] ?? [];
        $verdicts[$task->work_order_id] ??= WorkOrderVisibilityScope::scopeToActor(
            WorkOrder::query()->whereKey($task->work_order_id),
            $user,
        )->exists();
        self::$verdictsByActor[$user] = $verdicts;

        return $verdicts[$task->work_order_id];
    }
}

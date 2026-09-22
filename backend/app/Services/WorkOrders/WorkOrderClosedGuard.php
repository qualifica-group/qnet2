<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\WorkOrder;

/**
 * THE single check behind D-9's "commessa chiusa" veto (spec 0146): every
 * mutation of the task board — stage CRUD/reorder/close/reopen, move, bulk —
 * refuses with 409 while `$workOrder->is_force_closed` is true, and the board
 * READ endpoint mirrors the same flag into its own `is_read_only` (D-9,
 * `WorkOrderTaskBoardService`/`TaskBoardQuery`) rather than re-deriving it.
 * Static and stateless, the same shape as `App\Services\Tasks\TaskWriteLock`:
 * every caller here is itself a Service already injected elsewhere, so no DI
 * wiring is worth adding for a single boolean check.
 */
final class WorkOrderClosedGuard
{
    public static function assertOpen(WorkOrder $workOrder): void
    {
        abort_if($workOrder->is_force_closed, 409, 'This work order is closed.');
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions\Tasks;

use RuntimeException;

/**
 * Thrown by App\Services\Tasks\TaskBulkService when one or more rows of a
 * `POST /api/tasks/bulk` request (spec 0156, D-6) cannot go through the
 * requested action — never a partial success: the outer transaction the
 * Service runs inside rolls back entirely before this reaches the
 * controller. App\Http\Controllers\Tasks\TaskBulkController catches it ahead
 * of the generic exception handler, to emit `incompatible_tasks` at the
 * envelope's ROOT (the frozen contract's own shape), which
 * BaseApiController::fail()'s `$data` parameter cannot express (that one
 * nests under `data`).
 */
final class TaskBulkIncompatibleException extends RuntimeException
{
    /**
     * @param  array<int, array{id: int, reason: string}>  $incompatibleTasks
     */
    public function __construct(public readonly array $incompatibleTasks)
    {
        parent::__construct('One or more tasks are not compatible with this bulk action.');
    }
}

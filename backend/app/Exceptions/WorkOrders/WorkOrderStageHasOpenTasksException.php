<?php

declare(strict_types=1);

namespace App\Exceptions\WorkOrders;

use RuntimeException;

/**
 * Thrown by WorkOrderStageService::close() when $stage still has at least one
 * non-closed Task — root or sub-task (spec 0146, D-4/AC-008). Caught by
 * WorkOrderStageController::close() (before the generic Throwable handler) to
 * build the frozen 409 body `{ success:false, message, data:
 * { open_tasks_count } }`, the one endpoint in this module whose error body
 * carries `data` — never surfaced as the generic `errors` shape
 * BaseApiController::fail() produces for every other 4xx.
 */
final class WorkOrderStageHasOpenTasksException extends RuntimeException
{
    public function __construct(private readonly int $openTasksCount)
    {
        parent::__construct('This stage still has open tasks: close them before closing the stage.');
    }

    public function openTasksCount(): int
    {
        return $this->openTasksCount;
    }
}

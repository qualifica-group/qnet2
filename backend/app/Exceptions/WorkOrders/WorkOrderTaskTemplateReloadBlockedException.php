<?php

declare(strict_types=1);

namespace App\Exceptions\WorkOrders;

use RuntimeException;

/**
 * Thrown by WorkOrderTaskTemplateReloader when at least one task of the
 * current template is already completed (spec 0399, D-3). The controller
 * turns it into the 409 body `{ success:false, message, data:
 * { completed_tasks_count } }`.
 */
final class WorkOrderTaskTemplateReloadBlockedException extends RuntimeException
{
    public function __construct(private readonly int $completedTasksCount)
    {
        parent::__construct('Some tasks of the current template are already completed: the template cannot be reloaded.');
    }

    public function completedTasksCount(): int
    {
        return $this->completedTasksCount;
    }
}

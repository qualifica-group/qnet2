<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `task-priorities` resource (spec 0101). No special
 * overrides: every ability maps to "task-priorities.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the TaskPriority model and by
 * `permissions:sync` from this file's own reflection.
 */
class TaskPriorityPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'task-priorities';
    }
}

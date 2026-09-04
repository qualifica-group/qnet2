<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `task-types` resource (spec 0101). No special
 * overrides: every ability maps to "task-types.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the TaskType model and by
 * `permissions:sync` from this file's own reflection.
 */
class TaskTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'task-types';
    }
}

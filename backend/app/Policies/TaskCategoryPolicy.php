<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `task-categories` resource (spec 0101). No special
 * overrides: every ability maps to "task-categories.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the TaskCategory model and by
 * `permissions:sync` from this file's own reflection.
 */
class TaskCategoryPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'task-categories';
    }
}

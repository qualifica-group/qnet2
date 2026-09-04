<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `task-importances` resource (spec 0101). No special
 * overrides: every ability maps to "task-importances.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the TaskImportance model and by
 * `permissions:sync` from this file's own reflection.
 */
class TaskImportancePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'task-importances';
    }
}

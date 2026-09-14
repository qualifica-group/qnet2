<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `task-templates` resource (spec 0124). No
 * special overrides: every ability maps to "task-templates.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the TaskTemplate model.
 */
class TaskTemplatePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'task-templates';
    }
}

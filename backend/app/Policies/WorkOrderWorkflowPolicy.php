<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `work-order-workflows` resource (spec 0322):
 * every ability maps to "work-order-workflows.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the WorkOrderWorkflow model.
 */
class WorkOrderWorkflowPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'work-order-workflows';
    }
}

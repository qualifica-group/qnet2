<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `work-orders` resource (spec 0093). No
 * special overrides: every ability maps to "work-orders.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the WorkOrder model.
 */
class WorkOrderPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'work-orders';
    }
}

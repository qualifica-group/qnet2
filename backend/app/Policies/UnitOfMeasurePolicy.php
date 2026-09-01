<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `units-of-measure` resource (spec 0088). No
 * special overrides: every ability maps to "units-of-measure.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the UnitOfMeasure model.
 */
class UnitOfMeasurePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'units-of-measure';
    }
}

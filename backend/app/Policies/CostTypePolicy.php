<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `cost-types` resource (spec 0210): every
 * ability maps to "cost-types.{ability}" via BasePolicy.
 */
class CostTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'cost-types';
    }
}

<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `revenue-types` resource (spec 0348, D-13).
 */
class RevenueTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'revenue-types';
    }
}

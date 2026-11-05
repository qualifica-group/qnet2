<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `chart-accounts` resource (spec 0348, D-13).
 */
class ChartAccountPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'chart-accounts';
    }
}

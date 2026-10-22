<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `company-closures` resource (spec 0304 D-13).
 */
class CompanyClosurePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'company-closures';
    }
}

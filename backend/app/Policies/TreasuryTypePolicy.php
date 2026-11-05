<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `treasury-types` resource (spec 0360, D-3).
 */
class TreasuryTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'treasury-types';
    }
}

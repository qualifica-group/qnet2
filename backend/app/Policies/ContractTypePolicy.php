<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `contract-types` resource (spec 0290): every
 * ability maps to "contract-types.{ability}" via BasePolicy.
 */
class ContractTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'contract-types';
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `contract-statuses` resource (spec 0072).
 * No special overrides: every ability maps to "contract-statuses.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the ContractStatus model.
 */
class ContractStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'contract-statuses';
    }
}

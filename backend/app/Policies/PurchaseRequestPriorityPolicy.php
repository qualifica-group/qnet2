<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `purchase-request-priorities` resource (spec 0101). No special
 * overrides: every ability maps to "purchase-request-priorities.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the PurchaseRequestPriority model and by
 * `permissions:sync` from this file's own reflection.
 */
class PurchaseRequestPriorityPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'purchase-request-priorities';
    }
}

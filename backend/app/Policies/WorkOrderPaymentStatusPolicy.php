<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `work-order-payment-statuses` resource (spec
 * 0201): every ability maps to "work-order-payment-statuses.{ability}" via
 * BasePolicy.
 */
class WorkOrderPaymentStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'work-order-payment-statuses';
    }
}

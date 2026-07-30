<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `payment-methods` resource (spec 0068).
 * No special overrides: every ability maps to "payment-methods.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the PaymentMethod model.
 */
class PaymentMethodPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'payment-methods';
    }
}

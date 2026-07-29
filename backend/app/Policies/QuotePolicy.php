<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `quotes` resource (spec 0065, MT-05).
 * No special overrides: every ability maps to "quotes.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the Quote model.
 */
class QuotePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'quotes';
    }
}

<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `quote-statuses` resource (spec 0065).
 * No special overrides: every ability maps to "quote-statuses.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the QuoteStatus model.
 */
class QuoteStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'quote-statuses';
    }
}

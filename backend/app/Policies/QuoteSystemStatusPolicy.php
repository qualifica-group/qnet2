<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy of the `quote-system-statuses` resource (spec 0404 part A).
 */
class QuoteSystemStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'quote-system-statuses';
    }
}

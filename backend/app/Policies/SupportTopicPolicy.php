<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `support-topics` resource (spec 0334): every
 * ability maps to "support-topics.{ability}" via BasePolicy.
 */
class SupportTopicPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'support-topics';
    }
}

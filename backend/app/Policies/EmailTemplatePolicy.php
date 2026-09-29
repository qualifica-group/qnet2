<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `email-templates` resource (spec 0175, D-14).
 * No special overrides: every ability maps to "email-templates.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the EmailTemplate model and by
 * `permissions:sync` from this file's own reflection.
 */
class EmailTemplatePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'email-templates';
    }
}

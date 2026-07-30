<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `document-layouts` resource (spec 0069).
 * No special overrides: every ability maps to "document-layouts.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the DocumentLayout model.
 */
class DocumentLayoutPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'document-layouts';
    }
}

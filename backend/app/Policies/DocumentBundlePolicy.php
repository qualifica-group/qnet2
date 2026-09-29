<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `document-bundles` resource (spec 0175,
 * D-14). No special overrides: every ability maps to
 * "document-bundles.{ability}" via BasePolicy, auto-discovered by Laravel
 * from the DocumentBundle model and by `permissions:sync` from this file's
 * own reflection.
 */
class DocumentBundlePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'document-bundles';
    }
}

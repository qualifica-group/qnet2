<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `proforma-requests` resource (spec 0193). Overrides
 * `abilities()`: requests are never imported from a file, so `import` is
 * absent and `permissions:sync` never creates it.
 */
class ProformaRequestPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'proforma-requests';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'];
    }
}

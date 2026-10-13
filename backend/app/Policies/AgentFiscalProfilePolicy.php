<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `agent-fiscal-profiles` resource (spec 0212, D-10). Profiles
 * are never imported from a file, so `import` is absent from the abilities and
 * `permissions:sync` never creates it.
 */
class AgentFiscalProfilePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'agent-fiscal-profiles';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'];
    }
}

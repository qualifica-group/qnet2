<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `enasarco-parameters` resource (spec 0213, D-8). No import:
 * the yearly parameters are keyed in by hand.
 */
class EnasarcoParameterPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'enasarco-parameters';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'];
    }
}

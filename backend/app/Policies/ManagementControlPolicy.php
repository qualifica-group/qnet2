<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Permission catalogue entry of the "Controllo di gestione" module (spec 0268,
 * D-18): `management-control.view` opens both endpoints, the menu entry and the
 * page. Not bound to a model, so abilities() is reduced to `view` (same pattern
 * as LedgerPolicy). It is assignable from the Role form through the
 * `management-control` entry of config('authorization.definitions')
 * (ManagementControlAuthorization). The check itself is in the FormRequests'
 * authorize(); super-admin passes the global Gate::before.
 */
class ManagementControlPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'management-control';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view'];
    }
}

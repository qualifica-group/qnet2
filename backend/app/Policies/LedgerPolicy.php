<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Permission catalogue entry of the registry ledger (spec 0266, D-7):
 * `ledger.view` opens the endpoint, the menu entry, the page and the registry
 * tab. Not bound to a model, so abilities() is reduced to `view` and
 * SyncPermissions creates exactly that one (same pattern as LegacyIdPolicy).
 * It is assignable from the Role form through the `ledger` entry of
 * config('authorization.definitions') (LedgerAuthorization), which places it
 * in the Accounting area of the catalogue; the permission-only list would also
 * add it a second time to the "shared" area. The check itself is in
 * RegistryLedgerRequest::authorize(); super-admin passes the global Gate::before.
 */
class LedgerPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'ledger';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view'];
    }
}

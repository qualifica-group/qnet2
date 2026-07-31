<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `contracts` resource (spec 0072, MT-02, BR-8). Overrides
 * `abilities()`: a contract is never created or deleted by hand (D-6), so
 * `create`/`delete`/`import` are DELIBERATELY absent — `permissions:sync`
 * (late-static-bound on this override, BasePolicy::permissions()) never
 * creates `contracts.create`/`contracts.delete` (AC-037). The 5 domain-action
 * abilities (validate/terminate/schedule/changeStatus/reactivate, MT-03) are
 * resource-level, like `viewActivity` — the record-level rule for
 * `reactivate` (only when suspended) lives in ContractsAuthorization's
 * `actionPermissions()`, not here.
 */
class ContractPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'contracts';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'schedule', 'changeStatus', 'reactivate'];
    }

    public function validate(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('validate'));
    }

    public function terminate(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('terminate'));
    }

    public function schedule(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('schedule'));
    }

    public function changeStatus(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('changeStatus'));
    }

    public function reactivate(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('reactivate'));
    }
}

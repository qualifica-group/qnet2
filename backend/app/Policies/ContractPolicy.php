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
 * abilities (validate/terminate/program/changeStatus/reactivate, MT-03/spec
 * 0095 D-2) are resource-level, like `viewActivity` — the record-level rule
 * for `reactivate` (only when suspended) and for `program` (only on a
 * ClosedWon-group contract) lives in ContractsAuthorization's
 * `actionPermissions()`, not here: each caller of the two `program`-gated
 * endpoints (spec 0095) explicitly ANDs this ability with
 * ContractActionAvailability::mayProgram(), never folding the lifecycle rule
 * into the ability itself — SyncPermissions instantiates every Policy with a
 * bare `new $class` (no container), so this class stays constructor-free.
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
        return ['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate'];
    }

    public function validate(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('validate'));
    }

    public function terminate(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('terminate'));
    }

    /**
     * "Programma" (spec 0095, D-2): the ability alone — pure permission
     * check, like every other domain action here. The lifecycle gate
     * (ClosedWon group) is ANDed separately by each of the two callers
     * (ContractProgrammableLinesController/ContractWorkOrderController), via
     * ContractActionAvailability::mayProgram().
     */
    public function program(User $user, Contract $contract): bool
    {
        return $user->can($this->permission('program'));
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

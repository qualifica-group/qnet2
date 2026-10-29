<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Contracts\ContractVisibilityScope;
use App\Services\Quotes\QuoteVisibilityScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

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
 *
 * Record visibility (spec 0316, D-3/D-8): `view`, `update` and the 5 domain
 * actions need the permission AND the contract's quote to be in the actor's
 * scope (own, own site with `contracts.viewSite`, or `contracts.viewAll`).
 * `viewSite`/`viewAll` only narrow; they never grant `view`.
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
        return [
            'viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate',
            QuoteVisibilityScope::VIEW_SITE_ABILITY, QuoteVisibilityScope::VIEW_ALL_ABILITY,
        ];
    }

    public function view(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('view')), fn (): bool => $this->isInScope($user, $model), 'contract_out_of_scope');
    }

    public function update(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('update')), fn (): bool => $this->isInScope($user, $model), 'contract_out_of_scope');
    }

    /** Resource-level: lifts the record scoping to every contract. */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission(QuoteVisibilityScope::VIEW_ALL_ABILITY));
    }

    /** Resource-level: adds the contracts whose quote belongs to the actor's operational sites. */
    public function viewSite(User $user): bool
    {
        return $user->can($this->permission(QuoteVisibilityScope::VIEW_SITE_ABILITY));
    }

    public function validate(User $user, Contract $contract): Response
    {
        return $this->withinPerimeter($user->can($this->permission('validate')), fn (): bool => ContractVisibilityScope::isVisibleTo($user, $contract), 'contract_out_of_scope');
    }

    public function terminate(User $user, Contract $contract): Response
    {
        return $this->withinPerimeter($user->can($this->permission('terminate')), fn (): bool => ContractVisibilityScope::isVisibleTo($user, $contract), 'contract_out_of_scope');
    }

    /**
     * "Programma" (spec 0095, D-2): the ability alone — pure permission
     * check, like every other domain action here. The lifecycle gate
     * (ClosedWon group) is ANDed separately by each of the two callers
     * (ContractProgrammableLinesController/ContractWorkOrderController), via
     * ContractActionAvailability::mayProgram().
     */
    public function program(User $user, Contract $contract): Response
    {
        return $this->withinPerimeter($user->can($this->permission('program')), fn (): bool => ContractVisibilityScope::isVisibleTo($user, $contract), 'contract_out_of_scope');
    }

    public function changeStatus(User $user, Contract $contract): Response
    {
        return $this->withinPerimeter($user->can($this->permission('changeStatus')), fn (): bool => ContractVisibilityScope::isVisibleTo($user, $contract), 'contract_out_of_scope');
    }

    public function reactivate(User $user, Contract $contract): Response
    {
        return $this->withinPerimeter($user->can($this->permission('reactivate')), fn (): bool => ContractVisibilityScope::isVisibleTo($user, $contract), 'contract_out_of_scope');
    }

    private function isInScope(User $user, Model $model): bool
    {
        return ! $model instanceof Contract || ContractVisibilityScope::isVisibleTo($user, $model);
    }
}

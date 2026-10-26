<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BasePolicy
{
    /**
     * Gates the payment data of an employee (IBAN, holder) and the salary of
     * the contract conditions (spec 0272 D-5). Checked as a plain permission.
     */
    public const string MANAGE_COMPENSATION_PERMISSION = 'users.manageCompensation';

    protected function resource(): string
    {
        return 'users';
    }

    /**
     * A super-admin is changed (edit, activate/deactivate, avatar, contracts)
     * only by another super-admin. Gate::before grants a super-admin actor
     * every ability, so reaching this method means the actor is not one.
     */
    public function update(User $user, Model $model): bool
    {
        return ! $this->isPrivilegedTarget($model) && parent::update($user, $model);
    }

    /**
     * In addition to the standard `users.delete` permission, a user can never
     * delete their own account (it would lock them, or the last admin, out),
     * and only a super-admin deletes a super-admin.
     */
    public function delete(User $user, Model $model): bool
    {
        if ($user->is($model) || $this->isPrivilegedTarget($model)) {
            return false;
        }

        return parent::delete($user, $model);
    }

    /**
     * Standard CRUD abilities plus `impersonate` ("Login as customer", spec
     * 0050) and `manageCompensation` (spec 0272 D-5) — feeds
     * `php artisan permissions:sync` (SyncPermissions instantiates this class
     * and reads permissions(), late-static-bound on abilities()), so
     * overriding this is enough to create `users.impersonate` and
     * `users.manageCompensation`.
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return array_merge(parent::abilities(), ['impersonate', 'manageCompensation']);
    }

    /**
     * Gate for POST /users/{user}/impersonate and the table row action (spec
     * 0050, D-3). Only the two hard AUTHORIZATION boundaries live here — the
     * actor holds `users.impersonate`, and a non-super-admin actor may never
     * target a super-admin (privilege escalation). Self-impersonation and an
     * inactive target are deliberately NOT checked here: the frozen data
     * contract maps them to 422 (business-rule violation), not 403
     * (authorization denial), and Gate::before grants a super-admin actor
     * every ability without ever reaching this method — so both rules can
     * only be guaranteed unconditionally in ImpersonationService, which is
     * where they live.
     */
    public function impersonate(User $user, Model $model): bool
    {
        if (! $user->can($this->permission('impersonate'))) {
            return false;
        }

        return ! $this->isPrivilegedTarget($model);
    }

    /**
     * Whether $model is a super-admin, reserved to super-admin actors: every
     * caller runs only for a non-super-admin actor (Gate::before).
     */
    private function isPrivilegedTarget(Model $model): bool
    {
        /** @var User $model */
        return $model->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    }
}

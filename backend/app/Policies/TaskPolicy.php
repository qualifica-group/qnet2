<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Tasks\TaskAbilityResolver;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Database\Eloquent\Model;

/**
 * CRUD policy for the `tasks` resource (spec 0101), plus the visibility
 * scoping of D-9: a Task is readable and writable only by its creatore,
 * richiedente, assegnatari or osservatori, unless the actor holds
 * `tasks.viewAll` (or is super-admin).
 *
 * spec 0116 grafts the RECORD-ROLE matrix on top: `update`/`delete` now AND
 * `TaskAbilityResolver` alongside the existing scope, and three new
 * record-level abilities (`complete`/`validate`/`block`) combine the
 * permission, the D-9 scope AND the matching matrix row in one call.
 * `manageAll` is resource-level, mirroring `viewAll` below — D-2 makes
 * "gestore" a PERMISSION, not a fifth membership role.
 *
 * DESIGN NOTE, contrasted with `App\Policies\ContractPolicy`: ContractPolicy
 * keeps its domain-action abilities a pure permission check and leaves the
 * record rule entirely to `ContractsAuthorization::actionPermissions()`,
 * because Contracts have no concept of a role on the record. Tasks DO have
 * one (creatore/richiedente, assegnatario, osservatore, gestore), and that
 * role IS an authorization concern (spec 0116 D-1) — so here the matrix
 * enters the Policy itself. AVAILABILITY (which phase the current status is
 * in) does NOT enter here: that stays entirely in
 * `App\Services\Tasks\TaskActionAvailability`, which is availability, not
 * authorization.
 *
 * The rule NARROWS: being an assegnatario does not grant `tasks.view`, which
 * the parent call still requires (AC-062).
 *
 * `update`/`delete` are scoped by the SAME rule as `view` on purpose
 * (AC-063): a record an actor may not see is not a record they may edit or
 * delete by guessing its id.
 *
 * `viewActivity` stays resource-level (BasePolicy): the aggregated log's
 * per-record boundary is `view`, which PolicyActivityLogAuthorizer already
 * checks on the record itself. `viewDocuments` (spec 0117) is resource-level
 * for the same shape of reason, with one honest difference recorded here:
 * the attachment endpoints have NO per-record boundary to fall back on, so
 * this ability gates the section and nothing narrows it per Task.
 *
 * Zero-argument constructible on purpose — `permissions:sync` discovers
 * policies with `new $class`, which is why TaskVisibilityScope AND
 * TaskAbilityResolver are static.
 */
class TaskPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'tasks';
    }

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $this->isInScope($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model)
            && $this->isInScope($user, $model)
            && $model instanceof Task
            && TaskAbilityResolver::canUpdate($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model)
            && $this->isInScope($user, $model)
            && $model instanceof Task
            && TaskAbilityResolver::canDelete($user, $model);
    }

    /**
     * Resource-level gate lifting the membership scoping: with this ability
     * the actor sees every Task, not only the ones they take part in
     * (AC-061).
     */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission('viewAll'));
    }

    /**
     * Spec 0148: the middle visibility tier — besides their own Tasks, the
     * actor sees the Tasks of every assignee sharing one of their Sedi
     * (TaskVisibilityScope). Read-only widening, like `viewAll`.
     */
    public function viewSite(User $user): bool
    {
        return $user->can($this->permission('viewSite'));
    }

    /**
     * "Gestore" (D-2): resource-level, mirroring `viewAll` — a pure
     * permission check, no model in play. The admin-as-assignee deroga is a
     * record-level nuance that only matters once `TaskRecordRoles::isManager()`
     * is consulted against a specific Task, not here.
     */
    public function manageAll(User $user): bool
    {
        return $user->can($this->permission('manageAll'));
    }

    public function complete(User $user, Task $task): bool
    {
        return $user->can($this->permission('complete'))
            && $this->isInScope($user, $task)
            && TaskAbilityResolver::canComplete($user, $task);
    }

    public function validate(User $user, Task $task): bool
    {
        return $user->can($this->permission('validate'))
            && $this->isInScope($user, $task)
            && TaskAbilityResolver::canValidate($user, $task);
    }

    public function block(User $user, Task $task): bool
    {
        return $user->can($this->permission('block'))
            && $this->isInScope($user, $task)
            && TaskAbilityResolver::canBlock($user, $task);
    }

    /**
     * "Richiedi aggiornamento" (spec 0118, D-10): the seventh domain action,
     * and the only matrix row where the watcher is admitted alongside
     * creatore/richiedente/gestore — a plain assignee is not (AC-040).
     */
    public function requestUpdate(User $user, Task $task): bool
    {
        return $user->can($this->permission('requestUpdate'))
            && $this->isInScope($user, $task)
            && TaskAbilityResolver::canRequestUpdate($user, $task);
    }

    /**
     * Resource-level, exactly like OpportunityPolicy::viewDocuments (spec
     * 0117 D-8): it gates the documents tab of the detail, NOT the single
     * attachment. Each attachment endpoint keeps being authorized on its own
     * by AttachmentPolicy, which has no per-record boundary of any kind --
     * the documents of a Task behave precisely like those of an
     * Opportunita', as decided.
     */
    public function viewDocuments(User $user): bool
    {
        return $user->can($this->permission('viewDocuments'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewAll', 'viewSite', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'];
    }

    private function isInScope(User $user, Model $model): bool
    {
        return ! $model instanceof Task || TaskVisibilityScope::isVisibleTo($user, $model);
    }
}

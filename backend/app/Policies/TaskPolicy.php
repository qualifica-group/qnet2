<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Database\Eloquent\Model;

/**
 * CRUD policy for the `tasks` resource (spec 0101), plus the visibility
 * scoping of D-9: a Task is readable and writable only by its creatore,
 * richiedente, assegnatari or osservatori, unless the actor holds
 * `tasks.viewAll` (or is super-admin).
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
 * checks on the record itself.
 *
 * Zero-argument constructible on purpose — `permissions:sync` discovers
 * policies with `new $class`, which is why TaskVisibilityScope is static.
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
        return parent::update($user, $model) && $this->isInScope($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->isInScope($user, $model);
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
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewAll'];
    }

    private function isInScope(User $user, Model $model): bool
    {
        return ! $model instanceof Task || TaskVisibilityScope::isVisibleTo($user, $model);
    }
}

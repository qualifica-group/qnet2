<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Authorization\TasksAuthorization;
use App\Models\Task;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use App\Services\Tasks\TaskAbilityResolver;
use App\Services\Tasks\TaskWriteLock;
use Illuminate\Support\Facades\Gate;

/**
 * Row-level authorization surface for the tasks grid (spec 0156, D-5),
 * split out of TasksTableDefinition purely for its file-size budget
 * (engineering.md §6): actionsFor()'s allowed-action list and the two
 * Policy-plus-domain-guard AND-checks authorizeDelete()/authorizeUpdate()
 * use ride on the SAME Gate/TaskAbilityResolver/TaskWriteLock calls the
 * grid row-action buttons, the generic bulk-delete and the inline-edit
 * paths already resolve elsewhere in this domain — never a second
 * evaluation of the matrix.
 */
final class TaskRowActionResolver
{
    public function __construct(private readonly TasksAuthorization $authorization) {}

    /**
     * Allowed action keys for a single row, via TaskPolicy — which carries
     * the D-9 visibility scoping, answered in memory here because both
     * membership relations are eager-loaded in TasksTableDefinition::
     * baseQuery(). Spec 0156, D-5 adds the seven domain actions
     * (TasksAuthorization::actionPermissions(), the SAME matrix the
     * detail's own buttons read) plus `duplicate`/`notes`, both riding on
     * the `view`/`create` gates already resolved above rather than a
     * second Policy call.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Task $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if ($this->authorizeDelete($actor, $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        $canView = in_array('view', $allowed, true);

        if ($canView && $actor->can('tasks.create')) {
            $allowed[] = 'duplicate';
        }

        if ($canView) {
            $allowed[] = 'notes';
        }

        $permissions = $this->authorization->actionPermissions($actor, $row);

        foreach (TaskTableConstants::DOMAIN_ACTION_FLAGS as $flag) {
            if ($permissions[$flag] ?? false) {
                $allowed[] = $flag;
            }
        }

        return $allowed;
    }

    /**
     * Spec 0125, D-2: the Gate alone lets a super-admin assignee through, so
     * the delete row of the matrix is ANDed here too — the grid row-action
     * and the bulk-delete `forbidden` verdict then match the Service's 403.
     */
    public function authorizeDelete(User $actor, Task $row): bool
    {
        return Gate::forUser($actor)->allows('delete', $row) && TaskAbilityResolver::canDelete($actor, $row);
    }

    /**
     * Spec 0156, contract: `editable` of a row = update allowed AND the Task
     * is not closed/blocked for writing, a super-admin excluded from that
     * second half — the same coarse row-level UI hint `editable` already is
     * everywhere else in this engine (D-2 of spec 0053: "il config è un
     * suggerimento, la catena di guardie del PATCH è la verità"), so this
     * deliberately reads only $row's OWN `is_blocked`/status phase
     * (TaskWriteLock::isLocked()), never the ancestor-chain cascade
     * (TaskWriteLock::isLockedByAncestor()) — the per-field write TaskCellWriter
     * -> TaskService::update() runs is the one place that walk is judged for
     * real, and it stays a per-row query the grid's page cannot afford to
     * repeat for every locked-by-ancestor sub-task.
     */
    public function authorizeUpdate(User $actor, Task $row): bool
    {
        if (! Gate::forUser($actor)->allows('update', $row)) {
            return false;
        }

        if ($actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            return true;
        }

        return ! TaskWriteLock::isLocked($row);
    }
}

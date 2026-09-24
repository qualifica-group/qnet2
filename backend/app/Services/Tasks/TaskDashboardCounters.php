<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskAssignmentScope;
use App\Enums\TaskListStatus;
use App\Models\Task;
use App\Models\User;
use App\Tables\Tasks\TaskAdvancedFilterApplier;
use App\Tables\Tasks\TaskAdvancedFilterCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The Task counters of the `/dashboard` home (spec 0151, D-4/D-5/D-6):
 * `not_completed`, `assigned_to_me`, `assigned_by_me` (with its
 * `to_validate` chip), `created_by_me`, `observed_by_me`.
 *
 * D-6 (non-negotiable): every counter reuses TaskAdvancedFilterApplier —
 * the SAME predicate the `tasks` grid applies for `status`/`assignment` —
 * on top of TaskVisibilityScope, so a card's count can never disagree with
 * the list the frontend opens on its click (AC-005). Root tasks and
 * sub-tasks both count (D-4): the grid itself is flat, so scopeToActor()
 * alone is the only restriction needed here.
 *
 * `created_by_me` inherits the D-3 tightening of spec 0153 (creator, and
 * neither requester, assignee nor watcher) for free, since it goes through
 * the very same TaskAdvancedFilterApplier::applyScope() the grid's `assignment`
 * filter uses.
 */
final class TaskDashboardCounters
{
    public function __construct(private readonly TaskAdvancedFilterApplier $filterApplier) {}

    /**
     * @return array{
     *     not_completed: array{count: int, total_minutes: int},
     *     assigned_to_me: array{count: int, total_minutes: int},
     *     assigned_by_me: array{count: int, total_minutes: int, to_validate: array{count: int, total_minutes: int}},
     *     created_by_me: array{count: int, total_minutes: int},
     *     observed_by_me: array{count: int, total_minutes: int},
     * }
     */
    public function handle(User $actor): array
    {
        // Step 1: not_completed — every visible open task, no assignment restriction.
        $notCompleted = $this->aggregate($actor, TaskListStatus::Open, null);

        // Step 2: the four assignment-scoped buckets, all on the open phases.
        $assignedToMe = $this->aggregate($actor, TaskListStatus::Open, TaskAssignmentScope::AssignedToMe);
        $assignedByMe = $this->aggregate($actor, TaskListStatus::Open, TaskAssignmentScope::AssignedByMe);
        $createdByMe = $this->aggregate($actor, TaskListStatus::Open, TaskAssignmentScope::CreatedByMe);
        $observedByMe = $this->aggregate($actor, TaskListStatus::Open, TaskAssignmentScope::ObservedByMe);

        // Step 3: the "to validate" chip — assigned_by_me narrowed to in_validation.
        $toValidate = $this->aggregate($actor, TaskListStatus::InValidation, TaskAssignmentScope::AssignedByMe);

        return [
            'not_completed' => $notCompleted,
            'assigned_to_me' => $assignedToMe,
            'assigned_by_me' => [...$assignedByMe, 'to_validate' => $toValidate],
            'created_by_me' => $createdByMe,
            'observed_by_me' => $observedByMe,
        ];
    }

    /**
     * One COUNT/SUM aggregate query for a single card: `$status` is always
     * applied, `$assignment` only when the card narrows on it (`null` for
     * `not_completed`). `total_minutes` never surfaces NULL (COALESCE),
     * matching the data_contract's "null = 0".
     *
     * @return array{count: int, total_minutes: int}
     */
    private function aggregate(User $actor, TaskListStatus $status, ?TaskAssignmentScope $assignment): array
    {
        /** @var Builder<Task> $query */
        $query = TaskVisibilityScope::scopeToActor(Task::query(), $actor);

        $this->filterApplier->apply($query, TaskAdvancedFilterCatalog::STATUS, $status->value, $actor);

        if ($assignment !== null) {
            $this->filterApplier->apply($query, TaskAdvancedFilterCatalog::ASSIGNMENT, $assignment->value, $actor);
        }

        // A `select` of two DB::raw() expressions, deliberately NOT the raw
        // query-builder shortcut method the Task module's own hygiene guard
        // bans outright (TaskModuleHygieneTest AC-073); a static, non-
        // interpolated SQL fragment stays allowed either way.
        /** @var object{count: int, total_minutes: int} $row */
        $row = $query
            ->select([
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(tasks.estimated_minutes), 0) as total_minutes'),
            ])
            ->first();

        return [
            'count' => (int) $row->count,
            'total_minutes' => (int) $row->total_minutes,
        ];
    }
}

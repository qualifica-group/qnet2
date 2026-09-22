<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Enums\TaskAssignmentScope;
use App\Enums\TaskDueWindow;
use App\Enums\TaskListStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies the three DERIVED advanced filters of the `tasks` domain (spec 0147,
 * D-3) with the same semantics as the work-order Task board
 * (`task-board-filters.ts`): phase buckets for `status`, the
 * `COALESCE(end_date, start_date)` due reference for `due`, the acting user
 * for `assignment`. An unknown enum value restricts nothing (the generic
 * validator only checks the value is scalar).
 */
final class TaskAdvancedFilterApplier
{
    /**
     * The board's own due reference: the end date, falling back to the start
     * date. `DATE()` keeps the comparison on the calendar day whichever way the
     * driver stores a `date` column (SQLite keeps the time part).
     */
    public const string DUE_REFERENCE_SQL = 'DATE(COALESCE(tasks.end_date, tasks.start_date))';

    private const array DERIVED_FILTERS = [
        TaskAdvancedFilterCatalog::STATUS,
        TaskAdvancedFilterCatalog::DUE,
        TaskAdvancedFilterCatalog::ASSIGNMENT,
    ];

    /**
     * Applies `$name` when it is one of the derived filters; false hands it
     * back to the generic engine.
     *
     * @param  Builder<Task>  $query
     */
    public function apply(Builder $query, string $name, mixed $value, ?User $actor): bool
    {
        if (! in_array($name, self::DERIVED_FILTERS, true)) {
            return false;
        }

        $raw = is_scalar($value) ? (string) $value : '';

        match ($name) {
            TaskAdvancedFilterCatalog::STATUS => $this->applyStatus($query, TaskListStatus::tryFrom($raw)),
            TaskAdvancedFilterCatalog::DUE => $this->applyDue($query, TaskDueWindow::tryFrom($raw)),
            default => $this->applyAssignment($query, TaskAssignmentScope::tryFrom($raw), $actor),
        };

        return true;
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyStatus(Builder $query, ?TaskListStatus $status): void
    {
        if ($status === TaskListStatus::Blocked) {
            $query->where('tasks.is_blocked', true);

            return;
        }

        $groups = $status?->groups();

        if ($groups !== null) {
            $query->whereHas('taskStatus', static fn (Builder $statuses) => $statuses->whereIn('group', $groups));
        }
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyDue(Builder $query, ?TaskDueWindow $window): void
    {
        if ($window === null) {
            return;
        }

        $today = Carbon::today();
        $reference = DB::raw(self::DUE_REFERENCE_SQL);

        match ($window) {
            TaskDueWindow::Today => $query->where($reference, '=', $today->toDateString()),
            TaskDueWindow::Overdue => $query->where($reference, '<', $today->toDateString()),
            TaskDueWindow::ThisWeek => $query->whereBetween($reference, [
                $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ]),
        };
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyAssignment(Builder $query, ?TaskAssignmentScope $scope, ?User $actor): void
    {
        if ($scope === null) {
            return;
        }

        if ($actor === null) {
            $query->whereNull('tasks.id');

            return;
        }

        match ($scope) {
            TaskAssignmentScope::AssignedToMe => $query->whereHas('assignees', static fn (Builder $assignees) => $assignees->whereKey($actor->id)),
            TaskAssignmentScope::RequestedByMe => $query->where('tasks.requester_id', $actor->id),
        };
    }
}

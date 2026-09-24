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
 *
 * Spec 0151 D-6 reuses this SAME class for the dashboard's Task counters
 * (App\Services\Tasks\TaskDashboardCounters): `in_validation` on `status` and
 * `assigned_by_me`/`created_by_me`/`observed_by_me` on `assignment` are the
 * dashboard's own buckets (D-3/D-5), applied with the exclusive semantics
 * documented on App\Enums\TaskAssignmentScope so the card counters and the
 * list they link to can never disagree.
 *
 * `assignment` (spec 0153, D-1) accepts either a single scalar (the
 * dashboard's own exclusive buckets, above) or an array of values, OR-combined
 * in one grouped WHERE so it composes with every other AND-ed filter. `all` is
 * the union of every role, applied even for an actor with `tasks.viewAll`
 * (that permission only lifts TaskVisibilityScope's own restriction).
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

        match ($name) {
            TaskAdvancedFilterCatalog::STATUS => $this->applyStatus($query, TaskListStatus::tryFrom($this->scalar($value))),
            TaskAdvancedFilterCatalog::DUE => $this->applyDue($query, TaskDueWindow::tryFrom($this->scalar($value))),
            default => $this->applyAssignment($query, $value, $actor),
        };

        return true;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
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
     * `$value` is a single scalar (the dashboard's exclusive buckets) or an
     * array of values (the grid's multi-select, spec 0153 D-1), OR-combined
     * inside ONE grouped WHERE so the whole filter still AND-combines with
     * every other active one. An unrecognised value restricts nothing, same
     * as an empty resolved set — the required-with-default machinery
     * (TableQueryBuilder::withRequiredDefaults) is what guarantees a REAL
     * scope reaches here in practice.
     *
     * @param  Builder<Task>  $query
     */
    private function applyAssignment(Builder $query, mixed $value, ?User $actor): void
    {
        $scopes = $this->resolveScopes($value);

        // `visible` in the OR set makes the whole group a no-op: the row set
        // is already TaskVisibilityScope's, which is exactly what it asks for.
        if ($scopes === [] || in_array(TaskAssignmentScope::Visible, $scopes, true)) {
            return;
        }

        if ($actor === null) {
            $query->whereNull('tasks.id');

            return;
        }

        $query->where(function (Builder $group) use ($scopes, $actor): void {
            foreach ($scopes as $scope) {
                $group->orWhere(fn (Builder $branch) => $this->applyScope($branch, $scope, $actor));
            }
        });
    }

    /**
     * @return array<int, TaskAssignmentScope>
     */
    private function resolveScopes(mixed $value): array
    {
        $raw = is_array($value) ? $value : [$value];
        $scopes = [];

        foreach ($raw as $item) {
            $scope = is_scalar($item) ? TaskAssignmentScope::tryFrom((string) $item) : null;

            if ($scope !== null) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes, SORT_REGULAR));
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyScope(Builder $query, TaskAssignmentScope $scope, User $actor): void
    {
        match ($scope) {
            TaskAssignmentScope::AssignedToMe => $query->whereHas('assignees', static fn (Builder $assignees) => $assignees->whereKey($actor->id)),
            TaskAssignmentScope::RequestedByMe => $query->where('tasks.requester_id', $actor->id),
            TaskAssignmentScope::AssignedByMe => $query
                ->where('tasks.requester_id', $actor->id)
                ->whereDoesntHave('assignees', static fn (Builder $assignees) => $assignees->whereKey($actor->id)),
            // Spec 0153, D-3: a creator who is ALSO the requester, an
            // assignee or a watcher counts under that other role, never here
            // too — hence the two extra whereDoesntHave() beyond the
            // pre-existing requester exclusion.
            TaskAssignmentScope::CreatedByMe => $query
                ->where('tasks.creator_id', $actor->id)
                ->where(fn (Builder $requester) => $requester
                    ->whereNull('tasks.requester_id')
                    ->orWhere('tasks.requester_id', '!=', $actor->id))
                ->whereDoesntHave('assignees', static fn (Builder $assignees) => $assignees->whereKey($actor->id))
                ->whereDoesntHave('watchers', static fn (Builder $watchers) => $watchers->whereKey($actor->id)),
            TaskAssignmentScope::ObservedByMe => $query->whereHas('watchers', static fn (Builder $watchers) => $watchers->whereKey($actor->id)),
            // Spec 0153, D-1: the union of every role — "task in cui ho un
            // ruolo" — applied even for an actor with `tasks.viewAll`.
            TaskAssignmentScope::All => $this->applyRoleUnion($query, $actor),
            // Handled up front in applyAssignment(): never reaches a branch.
            TaskAssignmentScope::Visible => null,
        };
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyRoleUnion(Builder $query, User $actor): void
    {
        $query
            ->where('tasks.requester_id', $actor->id)
            ->orWhere('tasks.creator_id', $actor->id)
            ->orWhereHas('assignees', static fn (Builder $assignees) => $assignees->whereKey($actor->id))
            ->orWhereHas('watchers', static fn (Builder $watchers) => $watchers->whereKey($actor->id));
    }
}

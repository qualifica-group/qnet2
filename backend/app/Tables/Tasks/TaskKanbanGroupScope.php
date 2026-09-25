<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Server-side Kanban column grouping for the `tasks` domain (spec 0164,
 * D-2): narrows the query to one column of either board — "per stato"
 * (`by: 'status'`, one task_status_id) or "per scadenza" (`by: 'due'`, one
 * of the seven fixed buckets the frontend used to classify client-side,
 * `task-kanban-due-buckets.ts`). Static and stateless, split out of
 * TasksTableDefinition purely for its file-size budget (engineering.md §6)
 * — `TasksTableDefinition::applyKanbanGroupScope()` delegates here, called
 * by `TableService::rows()` AFTER every column/advanced filter and the
 * quick search are already applied (like TaskTreeScope), so a column
 * combines with the rest of the active query.
 *
 * The due classification mirrors `task-kanban-due-buckets.ts`'s
 * `classifyTaskDueBucket()` EXACTLY: a closed task (`taskStatus.group` in
 * closed_positive/closed_negative) is always `completed`, regardless of its
 * dates. Among OPEN tasks the reference date (`TaskAdvancedFilterApplier::
 * DUE_REFERENCE_SQL`, `end_date ?? start_date`) is matched in the SAME
 * order the frontend uses — overdue, today, tomorrow, the rest of the
 * current ISO week (Mon-Sun), the rest of the current calendar month (which
 * also catches a task with NO reference date at all) — anything left over
 * is `later`. `today` is computed in the app's own configured timezone
 * (`Carbon::today()`), never the client's.
 */
final class TaskKanbanGroupScope
{
    public const string BY_STATUS = 'status';

    public const string BY_DUE = 'due';

    /**
     * @var array<int, string>
     */
    private const array CLOSED_GROUPS = [
        TaskStatusGroup::ClosedPositive->value,
        TaskStatusGroup::ClosedNegative->value,
    ];

    /**
     * @param  Builder<Task>  $query
     * @param  array{by: string, key: int|string}  $kanbanGroup
     */
    public static function apply(Builder $query, array $kanbanGroup): void
    {
        match ($kanbanGroup['by']) {
            self::BY_STATUS => self::applyStatus($query, (int) $kanbanGroup['key']),
            self::BY_DUE => self::applyDue($query, (string) $kanbanGroup['key']),
            // Unreachable: TableRowsRequest already 422s any other `by`.
            default => null,
        };
    }

    /**
     * @param  Builder<Task>  $query
     */
    private static function applyStatus(Builder $query, int $statusId): void
    {
        $query->where('tasks.task_status_id', $statusId);
    }

    /**
     * @param  Builder<Task>  $query
     */
    private static function applyDue(Builder $query, string $key): void
    {
        if ($key === 'completed') {
            self::scopeClosed($query);

            return;
        }

        self::scopeOpen($query);

        $today = Carbon::today();
        $reference = DB::raw(TaskAdvancedFilterApplier::DUE_REFERENCE_SQL);

        match ($key) {
            'overdue' => $query->whereNotNull($reference)->where($reference, '<', $today->toDateString()),
            'today' => $query->where($reference, '=', $today->toDateString()),
            'tomorrow' => $query->where($reference, '=', $today->copy()->addDay()->toDateString()),
            'this_week' => self::scopeThisWeek($query, $reference, $today),
            'this_month' => self::scopeThisMonth($query, $reference, $today),
            'later' => $query->whereNotNull($reference)->where($reference, '>', $today->copy()->endOfMonth()->toDateString()),
            // Unreachable: TableRowsRequest already 422s any other `due` key.
            default => null,
        };
    }

    /**
     * Strictly after tomorrow, through Sunday of the current ISO week —
     * `today`/`tomorrow` are matched first (above), so this never re-catches
     * them, even on a week whose end already IS tomorrow.
     *
     * @param  Builder<Task>  $query
     */
    private static function scopeThisWeek(Builder $query, Expression $reference, Carbon $today): void
    {
        $query->whereNotNull($reference)
            ->where($reference, '>', $today->copy()->addDay()->toDateString())
            ->where($reference, '<=', $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString());
    }

    /**
     * The rest of the current calendar month, PLUS a task with no reference
     * date at all (`classifyTaskDueBucket()`'s `reference === null` branch).
     *
     * @param  Builder<Task>  $query
     */
    private static function scopeThisMonth(Builder $query, Expression $reference, Carbon $today): void
    {
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $query->where(function (Builder $group) use ($reference, $weekEnd, $monthEnd): void {
            $group->whereNull($reference)
                ->orWhere(function (Builder $inner) use ($reference, $weekEnd, $monthEnd): void {
                    $inner->where($reference, '>', $weekEnd)->where($reference, '<=', $monthEnd);
                });
        });
    }

    /**
     * @param  Builder<Task>  $query
     */
    private static function scopeClosed(Builder $query): void
    {
        $query->whereHas('taskStatus', static fn (Builder $statuses) => $statuses->whereIn('group', self::CLOSED_GROUPS));
    }

    /**
     * @param  Builder<Task>  $query
     */
    private static function scopeOpen(Builder $query): void
    {
        $query->whereDoesntHave('taskStatus', static fn (Builder $statuses) => $statuses->whereIn('group', self::CLOSED_GROUPS));
    }
}

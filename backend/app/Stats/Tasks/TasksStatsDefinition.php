<?php

declare(strict_types=1);

namespace App\Stats\Tasks;

use App\Enums\TaskListStatus;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\Tasks\TaskVisibilityScope;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use App\Tables\Tasks\TaskAdvancedFilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `tasks` module (spec 0147): the work-order Task
 * board's KPIs (`task-board-metrics.ts`) — overdue, due today, estimated and
 * actual minutes — then the breakdown by status and by priority and the
 * monthly creation trend.
 *
 * Counted over ROOT tasks only, like the board (D-5), and always narrowed to
 * what the actor may see (TaskVisibilityScope, D-9 of spec 0101): unlike the
 * other modules, a Task panel must never reveal counts of hidden rows.
 */
class TasksStatsDefinition extends AbstractStatsDefinition
{
    public function domain(): string
    {
        return 'tasks';
    }

    public function modelClass(): string
    {
        return Task::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $today = Carbon::today()->toDateString();
        $total = $this->roots()->count();

        return [
            $this->stat('overdue', $this->overdueCount($today), icon: 'alert-triangle'),
            $this->stat(
                key: 'due_today',
                value: $this->roots()->where(DB::raw(TaskAdvancedFilterApplier::DUE_REFERENCE_SQL), '=', $today)->count(),
                icon: 'calendar-clock',
            ),
            $this->stat(
                key: 'estimated_minutes',
                value: (int) $this->roots()->sum('tasks.estimated_minutes'),
                format: StatFormat::Duration,
                icon: 'timer',
            ),
            $this->stat(
                key: 'actual_minutes',
                value: (int) TimeEntry::query()->whereIn('task_id', $this->roots()->select('tasks.id'))->sum('minutes'),
                format: StatFormat::Duration,
                icon: 'clock',
            ),
            $this->distribution(
                key: 'by_status',
                items: Aggregates::topRelated(
                    query: $this->roots()->toBase(),
                    foreignKey: 'tasks.task_status_id',
                    relatedTable: 'task_statuses',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                    colorColumn: 'color',
                ),
                total: $total,
                chart: DistributionChart::Donut,
            ),
            $this->distribution(
                key: 'by_priority',
                items: Aggregates::topRelated(
                    query: $this->roots()->toBase(),
                    foreignKey: 'tasks.task_priority_id',
                    relatedTable: 'task_priorities',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                    colorColumn: 'color',
                ),
                total: $total,
                chart: DistributionChart::Stacked,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(
                    'tasks',
                    'created_at',
                    self::TREND_MONTHS,
                    fn ($query) => $query->whereIn('tasks.id', $this->roots()->select('tasks.id')),
                ),
                chart: TrendChart::Columns,
                tone: 2,
            ),
        ];
    }

    /**
     * The actor's visible root tasks, a fresh builder per widget.
     *
     * @return Builder<Task>
     */
    private function roots(): Builder
    {
        return TaskVisibilityScope::scopeToActor(Task::query()->whereNull('tasks.parent_task_id'), Auth::user());
    }

    /** "Scaduto" = not in a closing phase and due before today (board's `isOverdue`). */
    private function overdueCount(string $today): int
    {
        $closingGroups = TaskListStatus::Completed->groups();

        return $this->roots()
            ->whereHas('taskStatus', static fn (Builder $statuses) => $statuses->whereNotIn('group', $closingGroups))
            ->where(DB::raw(TaskAdvancedFilterApplier::DUE_REFERENCE_SQL), '<', $today)
            ->count();
    }
}

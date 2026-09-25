<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkOrderStage entity (spec 0146, D-2/D-4): a commessa's own "Fase" —
 * copied verbatim from its `TaskTemplate`'s stages at generation time, then
 * free-standing: created, renamed, reordered, closed/reopened and deleted on
 * the commessa itself, with no catalog and no back-reference to the
 * template it came from.
 *
 * `closed_at`/`closed_by_id` are DELIBERATELY absent from `#[Fillable]`: the
 * close/reopen service action writes them directly after checking D-4's own
 * "no open task" guard, the same category as `WorkOrder::code`.
 */
#[Fillable(['name', 'sort_order'])]
class WorkOrderStage extends BaseModel
{
    /** @use HasFactory<WorkOrderStageFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The commessa this stage belongs to.
     *
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * The user who closed the stage, if it is currently closed (D-4).
     *
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    /**
     * The root Tasks assigned to this stage, in board position order —
     * mirrors `WorkOrder::tasks()`'s own ordering.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('stage_position');
    }

    /**
     * The segnatempo voci snapshotted onto this stage (spec 0163, D-1/D-4).
     * Read via `withSum('timeEntries as logged_minutes', 'minutes')` for the
     * board's per-fase total — never loaded whole, the same idiom
     * `Task::timeEntries()` already documents for `actual_minutes`.
     *
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Whether the stage is currently closed (D-4): a stage with an open Task
     * inside it can never reach this state, so this method is a plain
     * attribute check, not a query.
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}

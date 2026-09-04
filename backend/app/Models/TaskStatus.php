<?php

namespace App\Models;

use App\Enums\TaskStatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TaskStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Task status lookup entity (spec 0101, D-4/D-5): the configurator behind
 * `tasks.task_status_id`, and the SINGLE source of a Task's completion
 * percentage (D-6) — `tasks` carries no such column, so changing this row's
 * `completion_percentage` moves every Task in this status at once, with no
 * backfill (AC-021).
 *
 * `system_key` is DELIBERATELY absent from #[Fillable] — never
 * mass-assignable, written only by the create migration, protected
 * afterwards by App\Services\Statuses\SystemStatusGuard. Unlike
 * ContractStatus there is no `group` column: the six keys of
 * App\Enums\TaskStatusSystemKey already ARE the phases (D-5), so a status
 * with a NULL `system_key` belongs to no phase and is therefore never a
 * closing one (the accepted consequence recorded in AC-034).
 *
 * `sort_order` stays fillable: server-managed by
 * App\Services\Statuses\StatusOrderManager, never accepted at the
 * FormRequest layer (AC-045).
 */
#[Fillable(['name', 'description', 'color', 'icon', 'sort_order', 'is_active', 'completion_percentage'])]
class TaskStatus extends BaseModel
{
    /** @use HasFactory<TaskStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system rows pinned to the HEAD of the sort_order sequence
     * (StatusOrderManager), in the order they must appear: the four WORKING
     * phases, `Open` at 0 through `InValidation`. Named by system_key, never
     * by label — the label is admin-configurable and means nothing to this
     * code (D-5). A custom status is never a closing one, so it belongs among
     * these, which is exactly where the manager places it — between the head
     * and the tail.
     *
     * @var array<int, TaskStatusSystemKey>
     */
    public const array SYSTEM_HEAD_KEYS = [
        TaskStatusSystemKey::Open,
        TaskStatusSystemKey::InProgress,
        TaskStatusSystemKey::Pending,
        TaskStatusSystemKey::InValidation,
    ];

    /**
     * The system rows pinned to the TAIL, in the order they must appear: the
     * two CLOSING phases. Pinned last so no custom row can ever be ordered
     * after a closing status.
     *
     * @var array<int, TaskStatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [
        TaskStatusSystemKey::ClosedPositive,
        TaskStatusSystemKey::ClosedNegative,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
            'completion_percentage' => 'int',
            'system_key' => TaskStatusSystemKey::class,
        ];
    }

    /**
     * The Tasks currently in this status — the "in use" set
     * TaskStatusService::delete() guards against (D-8b). `task_status_id` is
     * restrictOnDelete in the migration too: defense in depth.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Whether this is one of the six mandatory system rows rather than a
     * custom, admin-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /**
     * Whether reaching this status closes the Task — the trigger condition
     * of App\Services\Tasks\TaskClosureFeedbackGuard (D-7). A custom status
     * is never closing (D-5).
     */
    public function isClosing(): bool
    {
        return $this->system_key?->isClosing() ?? false;
    }
}

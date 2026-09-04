<?php

namespace App\Models;

use App\Enums\TaskStatusGroup;
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
 * mass-assignable, written only by the migrations, protected afterwards by
 * App\Services\Statuses\SystemStatusGuard. Since the 2026-09-04
 * rectification of D-5 it marks only the three PROTECTED rows
 * (App\Enums\TaskStatusSystemKey), NOT the phase: the phase is `group`
 * (App\Enums\TaskStatusGroup), exactly as on ContractStatus. `system_key`
 * is UNIQUE and could never carry a many-to-one classification — five
 * statuses share the `open` phase in the client's own vocabulary.
 *
 * `group` IS fillable: every row, system or custom, declares a phase, and a
 * custom row in a closing phase closes the Task like any other (isClosing()
 * below). On a system row SystemStatusGuard still rejects it, so the
 * migration's phase assignment stays put.
 *
 * `sort_order` stays fillable: server-managed by
 * App\Services\Statuses\StatusOrderManager, never accepted at the
 * FormRequest layer (AC-045).
 */
#[Fillable(['name', 'description', 'color', 'icon', 'sort_order', 'is_active', 'completion_percentage', 'group'])]
class TaskStatus extends BaseModel
{
    /** @use HasFactory<TaskStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system row pinned to the HEAD of the sort_order sequence
     * (StatusOrderManager): the single protected opening status, at 0.
     * Named by system_key, never by label — the label is admin-configurable
     * and means nothing to this code (D-5). Every other row, protected or
     * not, is placed between this head and the closing tail below.
     *
     * @var array<int, TaskStatusSystemKey>
     */
    public const array SYSTEM_HEAD_KEYS = [
        TaskStatusSystemKey::Open,
    ];

    /**
     * The system rows pinned to the TAIL, in the order they must appear: the
     * two protected CLOSING rows. Pinned last so no ordinary row is ever
     * ordered after them.
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
            'group' => TaskStatusGroup::class,
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
     * Whether this is one of the three protected system rows rather than an
     * ordinary, admin-managed status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /**
     * Whether reaching this status closes the Task — the trigger condition
     * of App\Services\Tasks\TaskClosureFeedbackGuard (D-7). Decided on the
     * PHASE, so an ordinary status an admin placed in a closing phase closes
     * the Task exactly like a protected one (D-5 as rectified 2026-09-04).
     */
    public function isClosing(): bool
    {
        return $this->group->isClosing();
    }
}

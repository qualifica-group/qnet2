<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TaskTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TaskTemplateItem entity (spec 0124, D-1): one row of a `TaskTemplate`,
 * what `App\Services\WorkOrders\WorkOrderTaskGenerator` copies onto a
 * generated Task (D-6, snapshot — never a live FK back from `tasks`).
 * `LogsModelActivity`, same as `QuoteWorkflowStatus` (the equivalent
 * header+rows child): a row's own edits/creations/deletions are audited
 * individually, not only as a diff on the header.
 *
 * `task_status_id` is nullable (D-4): an unset row falls back to
 * `TaskInitialStatusResolver` at generation time, so this relation is
 * deliberately NOT required the way `Task::taskStatus()`'s NOT NULL column
 * is. `HasAttachments` backs `items.*.attachments` (D-6) — files an admin
 * uploads here are the SOURCE `AttachmentService::copyTo()` physically
 * duplicates onto the generated Task's own `documents` collection.
 *
 * `task_template_stage_id` (spec 0146, D-2): the "Fase" this row sits in,
 * null for "Senza fase" — written by the same full-sync writer as every
 * other column here, never a dedicated endpoint.
 *
 * `parent_id` (spec 0172, D-1): the row this one sits directly under, up to
 * 3 levels below a root row, null for a root row — a sub-item never has its
 * own `task_template_stage_id` (D-3), it follows its root's.
 */
#[Fillable(['title', 'description', 'estimated_minutes', 'task_status_id', 'due_offset_days', 'sort_order', 'task_template_stage_id', 'parent_id'])]
class TaskTemplateItem extends BaseModel
{
    /** @use HasFactory<TaskTemplateItemFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_minutes' => 'int',
            'due_offset_days' => 'int',
            'sort_order' => 'int',
        ];
    }

    /**
     * The header this row belongs to.
     *
     * @return BelongsTo<TaskTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    /**
     * The "Fase" this row sits in (spec 0146, D-2), null for "Senza fase".
     *
     * @return BelongsTo<TaskTemplateStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(TaskTemplateStage::class, 'task_template_stage_id');
    }

    /**
     * The row this sub-item sits directly under (spec 0172, D-1), null for a
     * root row.
     *
     * @return BelongsTo<TaskTemplateItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The rows directly nested under this one (spec 0172, D-1), in display
     * order — empty for a leaf row or a root with no sub-items.
     *
     * @return HasMany<TaskTemplateItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * The initial status a generated Task takes when this row's own is
     * unset, disabled or moved out of the open/pending phase by the time
     * generation runs (D-4).
     *
     * @return BelongsTo<TaskStatus, $this>
     */
    public function taskStatus(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class);
    }
}

<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TaskTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TaskTemplate entity (spec 0124, D-1): the "Modello di Task" header —
 * name/description/is_active, full CRUD, mirroring ProductTypology's shape.
 * Its rows (`items()`) are written only through the header's own
 * create/update (D-1: no dedicated row endpoint), and it is referenced by
 * zero or more `WorkOrder`s it has generated Tasks for (`workOrders()`),
 * which is exactly the "in use" set `TaskTemplateService::delete()` guards
 * against (D-5, AC-011) — the FK on `work_orders.task_template_id` is
 * `restrictOnDelete`, defence in depth behind that guard.
 *
 * `HasAttachments` (spec 0128, D-3): the header owns the images embedded in
 * its own rich text `description`, under the reserved `rich_text`
 * collection — distinct from `items.*.attachments` (D-6, spec 0124), which
 * each row owns for itself.
 */
#[Fillable(['name', 'description', 'is_active'])]
class TaskTemplate extends BaseModel
{
    /** @use HasFactory<TaskTemplateFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The ordered rows of this template, always read in generation/display
     * order (D-1) — never left to insertion order, which the full-sync
     * writer's delete+recreate churn would not preserve.
     *
     * @return HasMany<TaskTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskTemplateItem::class)->orderBy('sort_order');
    }

    /**
     * The ordered "Fasi" of this template (spec 0146, D-2), written through
     * the same full-sync writer as `items()`. An item with no stage sits in
     * "Senza fase" (`task_template_stage_id` null), not a row here.
     *
     * @return HasMany<TaskTemplateStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(TaskTemplateStage::class)->orderBy('sort_order');
    }

    /**
     * The Commesse generated from this template — the referenced-by set
     * `TaskTemplateService::delete()` guards against with a 409 (D-5,
     * AC-011). Not `restrictOnDelete` alone: the guard fires BEFORE the
     * delete attempt, so the message can name the reason instead of
     * surfacing a raw FK violation.
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}

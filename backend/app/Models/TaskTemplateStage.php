<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TaskTemplateStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TaskTemplateStage entity (spec 0146, D-2): one "Fase" of a `TaskTemplate`,
 * written only through the header's own create/update full-sync writer — the
 * same shape as `TaskTemplateItem` (no dedicated endpoint for a row).
 *
 * `LogsModelActivity`, same as `TaskTemplateItem`: a stage's own
 * creations/renames/deletions are audited individually, not only as a diff
 * on the header.
 */
#[Fillable(['name', 'sort_order'])]
class TaskTemplateStage extends BaseModel
{
    /** @use HasFactory<TaskTemplateStageFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
        ];
    }

    /**
     * The header this stage belongs to.
     *
     * @return BelongsTo<TaskTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    /**
     * The items grouped under this stage, in display order — mirrors
     * `TaskTemplate::items()`'s own ordering.
     *
     * @return HasMany<TaskTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskTemplateItem::class)->orderBy('sort_order');
    }
}

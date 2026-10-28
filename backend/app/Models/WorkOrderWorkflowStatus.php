<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderWorkflowStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workflow status lookup entity (spec 0322): the "Stato lavorazione" pick
 * list row of a WorkOrder. Belongs to a workflow (`work_order_workflow_id`)
 * OR to the GLOBAL default set when null. `system_key`,
 * `work_order_workflow_id` and `old_id` (the legacy `progress.id`) are
 * DELIBERATELY absent from #[Fillable] — never mass-assignable, written only
 * by the migration seed, the service/writer that syncs a set and the legacy
 * migration source. `group` is a `WorkOrderStatus` value (D-7);
 * `progress_percentage` (D-9) is configuration only.
 */
#[Fillable(['name', 'description', 'color', 'sort_order', 'group', 'progress_percentage'])]
class WorkOrderWorkflowStatus extends BaseModel
{
    /** @use HasFactory<WorkOrderWorkflowStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'group' => WorkOrderStatus::class,
            'progress_percentage' => 'int',
            'old_id' => 'int',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkOrderWorkflow::class, 'work_order_workflow_id');
    }

    /**
     * Whether this is a pinned system row ('open'/'completed'/'closed')
     * rather than a custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}

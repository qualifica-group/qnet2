<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\WorkOrderWorkflowCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One matching criterion of a WorkOrderWorkflow (spec 0322, D-8): `field` is
 * an allow-list key (App\Support\WorkOrderWorkflows\
 * WorkOrderCriterionFieldRegistry), `value_id` the chosen value's id. A
 * workflow matches a work order only when EVERY one of its criteria matches
 * (AND). No activity log on this row (pure child collection of the workflow,
 * which already logs its own changes).
 */
#[Fillable(['work_order_workflow_id', 'field', 'value_id'])]
class WorkOrderWorkflowCriterion extends BaseModel
{
    /** @use HasFactory<WorkOrderWorkflowCriterionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_id' => 'int',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkOrderWorkflow::class, 'work_order_workflow_id');
    }
}

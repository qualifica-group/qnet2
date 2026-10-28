<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work order workflow configurator (spec 0322, D-6): a named, activatable set
 * of matching criteria (`criteria()`) plus its own working-state statuses
 * (`statuses()`) applied to a WorkOrder. Twin of QuoteWorkflow, without the
 * criteria signature column (the duplicate-combination rule lives in
 * WorkOrderWorkflowService).
 */
#[Fillable(['name', 'is_active'])]
class WorkOrderWorkflow extends BaseModel
{
    /** @use HasFactory<WorkOrderWorkflowFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<WorkOrderWorkflowCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(WorkOrderWorkflowCriterion::class);
    }

    /**
     * @return HasMany<WorkOrderWorkflowStatus, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(WorkOrderWorkflowStatus::class);
    }
}

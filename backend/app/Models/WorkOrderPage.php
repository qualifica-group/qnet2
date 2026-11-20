<?php

namespace App\Models;

use Database\Factories\WorkOrderPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The shared notes page of a project work order (spec 0420): one row per work
 * order, created lazily. `content` is sanitized HTML (profile `page`); `version`
 * drives the optimistic concurrency of the save. Not audited on purpose: an
 * autosave must not flood the activity log.
 */
#[Fillable(['work_order_id', 'content', 'version', 'updated_by_id'])]
class WorkOrderPage extends Model
{
    /** @use HasFactory<WorkOrderPageFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'work_order_id' => 'int',
            'version' => 'int',
            'updated_by_id' => 'int',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function polls(): HasMany
    {
        return $this->hasMany(WorkOrderPagePoll::class);
    }
}

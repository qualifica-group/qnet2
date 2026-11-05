<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One message of a work order's chat (spec 0359). Deletion is soft and the
 * body is nulled by the service (D-5); `user_id` becomes null when the author
 * is removed. Audited through activitylog, body before/after included (AC-024).
 */
#[Fillable(['work_order_id', 'user_id', 'body', 'edited_at'])]
class WorkOrderChatMessage extends BaseModel
{
    /** @use HasFactory<WorkOrderChatMessageFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'work_order_id' => 'int',
            'user_id' => 'int',
            'edited_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

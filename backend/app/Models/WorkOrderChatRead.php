<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\WorkOrderChatReadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user read marker of a work order's chat (spec 0354): the last message
 * the user has seen, from which the unread count is derived.
 */
#[Fillable(['work_order_id', 'user_id', 'last_read_message_id'])]
class WorkOrderChatRead extends BaseModel
{
    /** @use HasFactory<WorkOrderChatReadFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'work_order_id' => 'int',
            'user_id' => 'int',
            'last_read_message_id' => 'int',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

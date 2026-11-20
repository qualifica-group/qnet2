<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One vote of a user on a work order page poll (spec 0380, D-7): unique per
 * (poll, user), nominative.
 */
#[Fillable(['work_order_page_poll_id', 'user_id', 'option_key'])]
class WorkOrderPagePollVote extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'work_order_page_poll_id' => 'int',
            'user_id' => 'int',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(WorkOrderPagePoll::class, 'work_order_page_poll_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\WorkOrderPagePollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A poll embedded in a work order page (spec 0380, D-7). `options` is a list of
 * `{key, label}` with server-generated keys; the page content only stores the id.
 */
#[Fillable(['work_order_page_id', 'question', 'options', 'created_by_id'])]
class WorkOrderPagePoll extends Model
{
    /** @use HasFactory<WorkOrderPagePollFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'work_order_page_id' => 'int',
            'options' => 'array',
            'created_by_id' => 'int',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WorkOrderPage::class, 'work_order_page_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(WorkOrderPagePollVote::class, 'work_order_page_poll_id');
    }

    /**
     * @return list<string>
     */
    public function optionKeys(): array
    {
        return array_column($this->options, 'key');
    }
}

<?php

namespace App\Models;

use App\Enums\OutboundEmailRecurrenceEndReason;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
use App\Models\Abstracts\BaseModel;
use Database\Factories\OutboundEmailRecurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rule and series state of a scheduled OutboundEmail (spec 0362). Only the
 * rule is client-writable (#[Fillable]); the series state (`next_send_on`,
 * `last_sent_on`, `sent_count`, `end_reason`) is written by the scheduler and
 * the dispatcher through forceFill, like OutboundEmail's own status.
 */
#[Fillable(['frequency', 'interval', 'weekdays', 'month_mode', 'month_day', 'ordinal', 'ordinal_weekday', 'year_month', 'workdays_only', 'starts_on', 'ends_on'])]
class OutboundEmailRecurrence extends BaseModel
{
    /** @use HasFactory<OutboundEmailRecurrenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => TaskRecurrenceFrequency::class,
            'interval' => 'int',
            'weekdays' => 'array',
            'month_mode' => TaskRecurrenceMonthMode::class,
            'month_day' => 'int',
            'ordinal' => 'int',
            'ordinal_weekday' => 'int',
            'year_month' => 'int',
            'workdays_only' => 'boolean',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'next_send_on' => 'date:Y-m-d',
            'last_sent_on' => 'date:Y-m-d',
            'sent_count' => 'int',
            'end_reason' => OutboundEmailRecurrenceEndReason::class,
        ];
    }

    /**
     * The programmed email this rule belongs to.
     *
     * @return BelongsTo<OutboundEmail, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(OutboundEmail::class, 'outbound_email_id');
    }
}

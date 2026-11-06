<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OutboundEmailRecurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rule and series state of a programmed email (spec 0362 frozen
 * `data_contract`, shape `OutboundEmailRecurrence`).
 *
 * @mixin OutboundEmailRecurrence
 */
class OutboundEmailRecurrenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'frequency' => $this->frequency->value,
            'interval' => $this->interval,
            'weekdays' => $this->weekdays,
            'month_mode' => $this->month_mode?->value,
            'month_day' => $this->month_day,
            'ordinal' => $this->ordinal,
            'ordinal_weekday' => $this->ordinal_weekday,
            'year_month' => $this->year_month,
            'workdays_only' => $this->workdays_only,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'next_send_on' => $this->next_send_on?->toDateString(),
            'last_sent_on' => $this->last_sent_on?->toDateString(),
            'sent_count' => $this->sent_count,
            'end_reason' => $this->end_reason?->value,
        ];
    }
}

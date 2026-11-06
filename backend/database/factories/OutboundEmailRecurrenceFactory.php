<?php

namespace Database\Factories;

use App\Enums\TaskRecurrenceFrequency;
use App\Models\OutboundEmail;
use App\Models\OutboundEmailRecurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A daily rule starting today and ending in a month, due today.
 *
 * @extends Factory<OutboundEmailRecurrence>
 */
class OutboundEmailRecurrenceFactory extends Factory
{
    protected $model = OutboundEmailRecurrence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outbound_email_id' => OutboundEmail::factory(),
            'frequency' => TaskRecurrenceFrequency::Daily,
            'interval' => 1,
            'weekdays' => null,
            'month_mode' => null,
            'month_day' => null,
            'ordinal' => null,
            'ordinal_weekday' => null,
            'year_month' => null,
            'workdays_only' => false,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addMonth()->toDateString(),
            'next_send_on' => today()->toDateString(),
            'last_sent_on' => null,
            'sent_count' => 0,
            'end_reason' => null,
        ];
    }
}

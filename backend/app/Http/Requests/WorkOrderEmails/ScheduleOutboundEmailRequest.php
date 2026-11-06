<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderEmails;

use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/work-orders/{workOrder}/emails/{email}/schedule (spec 0362): the
 * `recurrence` rule of a programmed email. Same pairing rules as
 * App\Http\Requests\Tasks\TaskRecurrenceRules (which stays untouched), minus
 * the `ends`/`occurrence_count` choice: the end date is always mandatory (D-4)
 * and the start date cannot be in the past (D-12).
 *
 * Authorization stays in the controller (owner ability + author + status).
 */
class ScheduleOutboundEmailRequest extends FormRequest
{
    /** Frequencies whose occurrences fall on a day of the month. */
    private const array MONTH_BASED_FREQUENCIES = ['monthly', 'yearly'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'recurrence' => ['required', 'array'],
            'recurrence.frequency' => ['required', Rule::enum(TaskRecurrenceFrequency::class)],
            'recurrence.interval' => ['required', 'integer', 'min:1'],
            'recurrence.weekdays' => ['required_if:recurrence.frequency,weekly', 'prohibited_unless:recurrence.frequency,weekly', 'array', 'min:1'],
            'recurrence.weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'recurrence.month_mode' => ['nullable', 'prohibited_unless:recurrence.frequency,monthly,yearly', Rule::enum(TaskRecurrenceMonthMode::class)],
            'recurrence.month_day' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('recurrence.frequency'), self::MONTH_BASED_FREQUENCIES, true)
                    && $this->input('recurrence.month_mode') !== TaskRecurrenceMonthMode::Ordinal->value),
                'prohibited_unless:recurrence.frequency,monthly,yearly',
                'prohibited_if:recurrence.month_mode,ordinal',
                'integer',
                'between:1,31',
            ],
            'recurrence.ordinal' => ['required_if:recurrence.month_mode,ordinal', 'prohibited_unless:recurrence.month_mode,ordinal', 'integer', 'between:1,5'],
            'recurrence.ordinal_weekday' => ['required_if:recurrence.month_mode,ordinal', 'prohibited_unless:recurrence.month_mode,ordinal', 'integer', 'between:1,7'],
            'recurrence.year_month' => ['required_if:recurrence.frequency,yearly', 'prohibited_unless:recurrence.frequency,yearly', 'integer', 'between:1,12'],
            'recurrence.workdays_only' => ['sometimes', 'boolean'],
            'recurrence.starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'recurrence.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:recurrence.starts_on'],
        ];
    }

    /**
     * The validated rule, ready for OutboundEmailScheduler::schedule().
     *
     * @return array<string, mixed>
     */
    public function recurrence(): array
    {
        /** @var array<string, mixed> $recurrence */
        $recurrence = $this->safe()->input('recurrence');

        return $recurrence;
    }
}

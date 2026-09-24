<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
use Illuminate\Validation\Rule;

/**
 * The `recurrence` object's validation (spec 0120, data_contract; spec 0155,
 * D-1 extends it), shared by StoreTaskRequest and UpdateTaskRequest: both
 * endpoints validate the SUBMITTED object with the same shape. `null`/absent
 * never reach the inner rules (`sometimes`).
 *
 * `month_mode` is optional for monthly/yearly rules (null = `fixed`, the
 * pre-0155 meaning): `month_day` is required for them unless the mode is
 * `ordinal`, and refused for every other frequency or with `ordinal`;
 * `ordinal`/`ordinal_weekday` are gated on `month_mode,ordinal`.
 * `year_month` gates on `frequency,yearly` directly, independent of
 * `month_mode` — a yearly rule needs it under EITHER mode. `workdays_only`
 * is a plain boolean flag, admitted for any frequency (D-1): it only ever
 * FILTERS the dates the other fields already describe, so it carries no
 * conditional pairing of its own.
 */
final class TaskRecurrenceRules
{
    /** Frequencies whose occurrences fall on a day of the month. */
    private const array MONTH_BASED_FREQUENCIES = ['monthly', 'yearly'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'recurrence' => ['sometimes', 'nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', Rule::enum(TaskRecurrenceFrequency::class)],
            'recurrence.interval' => ['required_with:recurrence', 'integer', 'min:1'],
            'recurrence.weekdays' => ['required_if:recurrence.frequency,weekly', 'prohibited_unless:recurrence.frequency,weekly', 'array', 'min:1'],
            'recurrence.weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            // Optional: null means `fixed`, so a monthly rule written before
            // spec 0155 keeps validating and behaving exactly as before.
            'recurrence.month_mode' => ['nullable', 'prohibited_unless:recurrence.frequency,monthly,yearly', Rule::enum(TaskRecurrenceMonthMode::class)],
            'recurrence.month_day' => [
                Rule::requiredIf(static fn (): bool => in_array(request()->input('recurrence.frequency'), self::MONTH_BASED_FREQUENCIES, true)
                    && request()->input('recurrence.month_mode') !== TaskRecurrenceMonthMode::Ordinal->value),
                'prohibited_unless:recurrence.frequency,monthly,yearly',
                'prohibited_if:recurrence.month_mode,ordinal',
                'integer',
                'between:1,31',
            ],
            'recurrence.ordinal' => ['required_if:recurrence.month_mode,ordinal', 'prohibited_unless:recurrence.month_mode,ordinal', 'integer', 'between:1,5'],
            'recurrence.ordinal_weekday' => ['required_if:recurrence.month_mode,ordinal', 'prohibited_unless:recurrence.month_mode,ordinal', 'integer', 'between:1,7'],
            'recurrence.year_month' => ['required_if:recurrence.frequency,yearly', 'prohibited_unless:recurrence.frequency,yearly', 'integer', 'between:1,12'],
            'recurrence.workdays_only' => ['sometimes', 'boolean'],
            'recurrence.ends' => ['required_with:recurrence', Rule::enum(TaskRecurrenceEnd::class)],
            'recurrence.ends_on' => ['required_if:recurrence.ends,on_date', 'prohibited_unless:recurrence.ends,on_date', 'date', 'after:end_date'],
            'recurrence.occurrence_count' => ['required_if:recurrence.ends,after_count', 'prohibited_unless:recurrence.ends,after_count', 'integer', 'min:1'],
        ];
    }
}

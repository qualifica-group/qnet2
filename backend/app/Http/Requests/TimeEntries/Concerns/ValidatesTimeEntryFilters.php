<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries\Concerns;

use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Enums\TimeEntryDailyStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * The shared F rules (spec 0122, data_contract), one implementation for the
 * three endpoints that all accept F verbatim: GET /api/time-entries,
 * stats/overview, stats/pulse (`ListTimeEntriesRequest`/
 * `TimeEntryStatsRequest`). `date_to`'s `after_or_equal:date_from` only
 * fires when both are present (`sometimes`); the max-range check (AC-015)
 * needs both bounds together so it lives in `withTimeEntryFilterValidation`'s
 * `after()` hook instead of a single-field rule.
 */
trait ValidatesTimeEntryFilters
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function timeEntryFilterRules(): array
    {
        return [
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'period_preset' => ['sometimes', 'nullable', 'string', Rule::in(['day', 'week', 'month', 'year'])],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'task_type_ids' => ['sometimes', 'array'],
            'task_type_ids.*' => ['integer', Rule::exists('task_types', 'id')],
            'registry_ids' => ['sometimes', 'array'],
            'registry_ids.*' => ['integer', Rule::exists('registries', 'id')],
            'opportunity_ids' => ['sometimes', 'array'],
            'opportunity_ids.*' => ['integer', Rule::exists('opportunities', 'id')],
            'work_order_ids' => ['sometimes', 'array'],
            'work_order_ids.*' => ['integer', Rule::exists('work_orders', 'id')],
            'task_ids' => ['sometimes', 'array'],
            'task_ids.*' => ['integer', Rule::exists('tasks', 'id')],
            'daily_statuses' => ['sometimes', 'array'],
            'daily_statuses.*' => [Rule::in(TimeEntryDailyStatus::values())],
            'is_active' => ['sometimes', 'nullable', Rule::in(['true', 'false', '1', '0'])],
        ];
    }

    /**
     * AC-015: `date_from`/`date_to` together may not span more than
     * `config('time_entries.max_range_days')` days.
     */
    protected function withTimeEntryFilterValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateFrom = $this->input('date_from');
            $dateTo = $this->input('date_to');

            if (! is_string($dateFrom) || ! is_string($dateTo)) {
                return;
            }

            $maxRangeDays = (int) config('time_entries.max_range_days');
            $spanDays = CarbonImmutable::parse($dateFrom)->diffInDays(CarbonImmutable::parse($dateTo)) + 1;

            if ($spanDays > $maxRangeDays) {
                $validator->errors()->add('date_to', __('The period may not span more than :max days.', ['max' => $maxRangeDays]));
            }
        });
    }

    protected function timeEntryFilterData(): TimeEntryFilterData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TimeEntryFilterData::fromValidated($validated);
    }
}

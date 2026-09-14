<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/time-entries/exports/monthly (spec 0122, data_contract, AC-025):
 * `month`/`year` required, `user_ids[]` optional (absent = every user with
 * segnatempo that month). The future-month check (422 on `month`) compares
 * against "today" in Europe/Rome — the same timezone `TimeEntryPeriodResolver`
 * uses for D-7's own "today" math (`time-entries.exportMonthly` itself is
 * authorized in the controller, not here).
 */
class ExportMonthlyTimeEntriesRequest extends FormRequest
{
    private const string TIMEZONE = 'Europe/Rome';

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
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $month = $this->input('month');
            $year = $this->input('year');

            if (! is_numeric($month) || ! is_numeric($year)) {
                return;
            }

            // Both sides resolved in the SAME timezone (Europe/Rome): comparing
            // a calendar month built in the app's default timezone against
            // "now" in Rome would otherwise flag the current month as future
            // whenever the two zones disagree on the month boundary instant.
            $requestedMonth = CarbonImmutable::create((int) $year, (int) $month, 1, 0, 0, 0, self::TIMEZONE)->startOfMonth();
            $currentMonth = CarbonImmutable::now(self::TIMEZONE)->startOfMonth();

            if ($requestedMonth->greaterThan($currentMonth)) {
                $validator->errors()->add('month', __('The selected month may not be in the future.'));
            }
        });
    }

    public function month(): int
    {
        return (int) $this->validated('month');
    }

    public function year(): int
    {
        return (int) $this->validated('year');
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        return array_values(array_map(static fn (mixed $id): int => (int) $id, $this->validated('user_ids') ?? []));
    }
}

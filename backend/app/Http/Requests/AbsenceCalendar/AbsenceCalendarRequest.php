<?php

namespace App\Http\Requests\AbsenceCalendar;

use App\Services\Absences\Calendar\AbsenceCalendarScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * GET /api/absences/calendar (spec 0279). Any of the three calendar
 * permissions opens it; the period is capped so one response stays small.
 */
class AbsenceCalendarRequest extends FormRequest
{
    /** Two full months plus the slack of their lengths (spec 0279 data_contract). */
    public const int MAX_DAYS = 62;

    public function authorize(): bool
    {
        return AbsenceCalendarScope::forActor($this->user()) !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'business_function_id' => ['nullable', 'integer', 'exists:business_functions,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = CarbonImmutable::parse($this->string('from'))->diffInDays(CarbonImmutable::parse($this->string('to'))) + 1;

                if ($days > self::MAX_DAYS) {
                    $validator->errors()->add('to', __('absence_requests.calendar_period_too_long', ['max' => self::MAX_DAYS]));
                }
            },
        ];
    }
}

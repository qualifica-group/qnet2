<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Models\EmploymentProfile;
use App\Services\TimeEntries\DailyTargetResolver;
use App\Services\TimeEntries\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Duration of an absence request in minutes (spec 0278, D-6): a full-day
 * request is the working days of [starts_on, ends_on] times the person's daily
 * hours; an hourly one is a single working day with the requested minutes
 * (1..daily hours). A request without any working day is a 422 on `starts_on`.
 */
final class AbsenceDurationCalculator
{
    public function __construct(
        private readonly WorkCalendar $calendar,
        private readonly DailyTargetResolver $dailyTarget,
    ) {}

    public function minutes(int $userId, bool $isFullDay, string $startsOn, string $endsOn, ?int $requestedMinutes): int
    {
        // Step 1: the person's daily hours (profile or configured default)
        $daily = $this->dailyTarget->resolve(EmploymentProfile::query()->where('user_id', $userId)->first());

        // Step 2: full day -> working days x daily hours, hourly -> the requested minutes
        return $isFullDay
            ? $this->workingDays($startsOn, $endsOn) * $daily
            : $this->hourlyMinutes($startsOn, $requestedMinutes, $daily);
    }

    private function workingDays(string $startsOn, string $endsOn): int
    {
        $count = 0;

        for ($day = CarbonImmutable::parse($startsOn); $day->format('Y-m-d') <= $endsOn; $day = $day->addDay()) {
            if (! $this->calendar->isNonWorkingDay($day->format('Y-m-d'))) {
                $count++;
            }
        }

        if ($count === 0) {
            throw ValidationException::withMessages(['starts_on' => [__('absence_requests.no_working_day')]]);
        }

        return $count;
    }

    private function hourlyMinutes(string $day, ?int $requestedMinutes, int $daily): int
    {
        if ($this->calendar->isNonWorkingDay($day)) {
            throw ValidationException::withMessages(['starts_on' => [__('absence_requests.no_working_day')]]);
        }

        if ($requestedMinutes === null || $requestedMinutes < 1 || $requestedMinutes > $daily) {
            throw ValidationException::withMessages(['requested_minutes' => [__('absence_requests.minutes_out_of_range', ['max' => $daily])]]);
        }

        return $requestedMinutes;
    }
}

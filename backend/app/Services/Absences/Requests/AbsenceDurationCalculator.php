<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Enums\AbsenceDayPart;
use App\Models\EmploymentProfile;
use App\Services\Absences\Calendar\PersonNonWorkingDays;
use App\Services\Absences\Calendar\PersonWorkCalendar;
use App\Services\TimeEntries\DailyTargetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Duration of an absence request in minutes (spec 0278, D-6): a full-day
 * request is the working days of [starts_on, ends_on] times the person's daily
 * hours; an hourly one is a single working day with the requested minutes
 * (1..daily hours); a half day is a single working day of half the daily hours
 * (spec 0304 D-8). Working days come from the person's calendar, closures
 * included (D-14). A request without any working day is a 422 on `starts_on`.
 */
final class AbsenceDurationCalculator
{
    public function __construct(
        private readonly PersonWorkCalendar $calendar,
        private readonly DailyTargetResolver $dailyTarget,
    ) {}

    public function minutes(
        int $userId,
        bool $isFullDay,
        string $startsOn,
        string $endsOn,
        ?int $requestedMinutes,
        ?AbsenceDayPart $dayPart = null,
    ): int {
        // Step 1: the person's daily hours and non-working days
        $daily = $this->dailyMinutes($userId);
        $days = $this->calendar->load([$userId], $startsOn, $endsOn);

        // Step 2: full day -> working days x daily hours, half day -> half of them, hourly -> the requested minutes
        if ($isFullDay) {
            return $this->workingDays($userId, $days, $startsOn, $endsOn) * $daily;
        }

        return $this->singleDayMinutes($userId, $days, $startsOn, $dayPart === null ? $requestedMinutes : $this->halfDayMinutes($daily), $daily);
    }

    /** The person's current daily hours in minutes (profile or configured default). */
    public function dailyMinutes(int $userId): int
    {
        return $this->dailyTarget->resolve(EmploymentProfile::query()->where('user_id', $userId)->first());
    }

    /** Minutes of a half day, stored as the request's `requested_minutes` (spec 0304 D-8). */
    public function halfDayMinutes(int $dailyMinutes): int
    {
        return intdiv($dailyMinutes, 2);
    }

    private function workingDays(int $userId, PersonNonWorkingDays $days, string $startsOn, string $endsOn): int
    {
        $count = 0;

        for ($day = CarbonImmutable::parse($startsOn); $day->format('Y-m-d') <= $endsOn; $day = $day->addDay()) {
            if (! $days->isNonWorkingDay($userId, $day->format('Y-m-d'))) {
                $count++;
            }
        }

        if ($count === 0) {
            throw ValidationException::withMessages(['starts_on' => [__('absence_requests.no_working_day')]]);
        }

        return $count;
    }

    private function singleDayMinutes(int $userId, PersonNonWorkingDays $days, string $day, ?int $minutes, int $daily): int
    {
        if ($days->isNonWorkingDay($userId, $day)) {
            throw ValidationException::withMessages(['starts_on' => [__('absence_requests.no_working_day')]]);
        }

        if ($minutes === null || $minutes < 1 || $minutes > $daily) {
            throw ValidationException::withMessages(['requested_minutes' => [__('absence_requests.minutes_out_of_range', ['max' => $daily])]]);
        }

        return $minutes;
    }
}

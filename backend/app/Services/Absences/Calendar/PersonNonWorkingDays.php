<?php

declare(strict_types=1);

namespace App\Services\Absences\Calendar;

use App\Services\TimeEntries\WorkCalendar;

/**
 * Non-working days of a set of people over one period (spec 0304 D-14): the
 * national calendar (weekend, holidays) plus the holiday closures that apply
 * to each person. Built by PersonWorkCalendar, read without further queries.
 */
final class PersonNonWorkingDays
{
    public const string REASON_WEEKEND = 'weekend';

    public const string REASON_HOLIDAY = 'holiday';

    public const string REASON_CLOSURE = 'closure';

    /**
     * @param  array<int, array<string, string>>  $closures  user id => date => closure name
     */
    public function __construct(
        private readonly WorkCalendar $calendar,
        private readonly array $closures,
    ) {}

    public function isNonWorkingDay(int $userId, string $date): bool
    {
        return $this->reason($userId, $date) !== null;
    }

    /**
     * @return array{reason: string, name: string|null}|null null on a working day
     */
    public function reason(int $userId, string $date): ?array
    {
        if ($this->calendar->isWeekend($date)) {
            return ['reason' => self::REASON_WEEKEND, 'name' => null];
        }

        if ($this->calendar->isHoliday($date)) {
            return ['reason' => self::REASON_HOLIDAY, 'name' => null];
        }

        $closure = $this->closures[$userId][$date] ?? null;

        return $closure === null ? null : ['reason' => self::REASON_CLOSURE, 'name' => $closure];
    }

    public function closureName(int $userId, string $date): ?string
    {
        return $this->closures[$userId][$date] ?? null;
    }

    /**
     * Holiday closures that apply to the person in the loaded period, by date.
     *
     * @return list<array{date: string, name: string}>
     */
    public function closedDays(int $userId): array
    {
        $days = $this->closures[$userId] ?? [];
        ksort($days);

        return array_map(
            static fn (string $date, string $name): array => ['date' => $date, 'name' => $name],
            array_keys($days),
            array_values($days),
        );
    }
}

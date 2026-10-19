<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\DayAbsenceData;

/**
 * In-memory index of approved absences by user and date, built once per
 * request by `TimeEntryAbsenceResolver`. It also owns the single target
 * formula (spec 0278, D-3) shared by the day builder and the team pulse:
 * a full-day absence zeroes the day, hourly ones subtract their minutes.
 */
final class DayAbsenceIndex
{
    /**
     * @param  array<int, array<string, list<DayAbsenceData>>>  $byUserAndDate
     */
    public function __construct(private readonly array $byUserAndDate = []) {}

    /**
     * @return list<DayAbsenceData>
     */
    public function forDay(int $userId, string $date): array
    {
        return $this->byUserAndDate[$userId][$date] ?? [];
    }

    /**
     * @param  list<DayAbsenceData>  $absences
     */
    public static function reduceTarget(int $baseTargetMinutes, array $absences): int
    {
        $hourlyMinutes = 0;

        foreach ($absences as $absence) {
            if ($absence->isFullDay) {
                return 0;
            }

            $hourlyMinutes += $absence->minutes;
        }

        return max(0, $baseTargetMinutes - $hourlyMinutes);
    }
}

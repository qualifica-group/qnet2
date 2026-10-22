<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\DayAbsenceData;
use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Services\Absences\Calendar\PersonNonWorkingDays;
use Carbon\CarbonImmutable;

/**
 * Loads the APPROVED absences of the given people that intersect
 * [$dateFrom, $dateTo] with ONE query and spreads them over the days they
 * cover (spec 0278, D-3): a full-day request on every working day of the person (closures included, spec 0304) of its
 * range (minutes = the person's base target), an hourly one on `starts_on`
 * (minutes = requested minutes). Draft/submitted/rejected/cancelled never count.
 */
final class TimeEntryAbsenceResolver
{
    /**
     * @param  array<int, int>  $baseTargetByUserId  user id => base daily target minutes
     */
    public function resolve(array $baseTargetByUserId, string $dateFrom, string $dateTo, PersonNonWorkingDays $nonWorking): DayAbsenceIndex
    {
        if ($baseTargetByUserId === []) {
            return new DayAbsenceIndex;
        }

        $requests = AbsenceRequest::query()
            ->whereIn('user_id', array_keys($baseTargetByUserId))
            ->where('status', AbsenceRequestStatus::Approved)
            ->where('starts_on', '<=', $dateTo)
            ->where('ends_on', '>=', $dateFrom)
            ->with('absenceType:id,name')
            ->orderBy('id')
            ->get();

        $index = [];

        foreach ($requests as $request) {
            foreach ($this->coveredDates($request, $dateFrom, $dateTo, $nonWorking) as $date) {
                $index[$request->user_id][$date][] = new DayAbsenceData(
                    id: $request->id,
                    absenceTypeName: $request->absenceType->name,
                    isFullDay: $request->is_full_day,
                    minutes: $request->is_full_day
                        ? $baseTargetByUserId[$request->user_id]
                        : (int) ($request->requested_minutes ?? $request->minutes ?? 0),
                );
            }
        }

        return new DayAbsenceIndex($index);
    }

    /**
     * @return list<string>
     */
    private function coveredDates(AbsenceRequest $request, string $dateFrom, string $dateTo, PersonNonWorkingDays $nonWorking): array
    {
        $start = $request->starts_on->format('Y-m-d');

        if (! $request->is_full_day) {
            return $start >= $dateFrom && $start <= $dateTo ? [$start] : [];
        }

        $dates = [];
        $date = CarbonImmutable::createFromFormat('Y-m-d', max($start, $dateFrom));
        $last = min($request->ends_on->format('Y-m-d'), $dateTo);

        for (; $date->format('Y-m-d') <= $last; $date = $date->addDay()) {
            if (! $nonWorking->isNonWorkingDay($request->user_id, $date->format('Y-m-d'))) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Absences\Balances;

use App\Models\AbsenceAllowance;
use App\Models\EmploymentProfile;
use App\Services\TimeEntries\DailyTargetResolver;

/**
 * Sets the yearly allowance of many people at once from a number of days
 * (spec 0304 D-12): each person gets `annual_days` x their own daily minutes,
 * so a part-timer receives proportionally less. A null `annual_days` clears
 * the allowance back to the type default. The carry-over of an existing row is
 * never touched (a new row starts at 0).
 */
final class AbsenceAllowanceBulkWriter
{
    public function __construct(private readonly DailyTargetResolver $daily) {}

    /**
     * @param  list<int>  $userIds
     * @return int number of allowances written
     */
    public function write(int $absenceTypeId, int $year, array $userIds, ?float $annualDays): int
    {
        // Step 1: profiles and existing rows in bulk
        $profiles = EmploymentProfile::query()->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $existing = AbsenceAllowance::query()
            ->where('absence_type_id', $absenceTypeId)
            ->where('year', $year)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        // Step 2: one model save per person so the activity log records it
        foreach ($userIds as $userId) {
            $allowance = $existing->get($userId) ?? new AbsenceAllowance([
                'user_id' => $userId,
                'absence_type_id' => $absenceTypeId,
                'year' => $year,
                'carried_over_minutes' => 0,
            ]);
            $allowance->annual_minutes = $annualDays === null
                ? null
                : (int) round($annualDays * $this->daily->resolve($profiles->get($userId)));
            $allowance->save();
        }

        return count($userIds);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Absences\Balances;

use App\Models\AbsenceAllowance;
use Carbon\CarbonImmutable;

/**
 * Year-end carry-over (spec 0278 D-11): for every allowance of the previous
 * year, creates or updates the one of the target year with
 * `carried_over_minutes` = the previous year's residual (possibly negative).
 * The annual allowance is copied only when the target row does not exist yet,
 * so an HR-set value is never overwritten. Idempotent.
 */
final class AbsenceCarryOverService
{
    private const int CHUNK = 200;

    public function __construct(private readonly AbsenceBalanceBuilder $balances) {}

    /**
     * @return int number of allowances written for $year
     */
    public function carryOver(int $year): int
    {
        // Step 1: residuals are computed as of the first day of the new year,
        // so the previous year is "past" and every month has accrued
        $reference = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $count = 0;

        // Step 2: one target allowance per previous-year allowance
        AbsenceAllowance::query()
            ->with(['user', 'absenceType'])
            ->where('year', $year - 1)
            ->chunkById(self::CHUNK, function ($allowances) use ($year, $reference, &$count): void {
                foreach ($allowances as $previous) {
                    $this->writeFor($previous, $year, $reference);
                    $count++;
                }
            });

        return $count;
    }

    private function writeFor(AbsenceAllowance $previous, int $year, CarbonImmutable $reference): void
    {
        $residual = $this->balances->row($previous->user, $previous->absenceType, $year - 1, $reference)['residual_minutes'];

        // Model save (not query update) so the activity log records it.
        $target = AbsenceAllowance::query()->firstOrNew([
            'user_id' => $previous->user_id,
            'absence_type_id' => $previous->absence_type_id,
            'year' => $year,
        ]);

        if (! $target->exists) {
            $target->annual_minutes = $previous->annual_minutes;
        }

        $target->carried_over_minutes = $residual;
        $target->save();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Absences\Balances;

use App\Models\AbsenceAllowance;
use App\Models\AbsenceType;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Year-end carry-over (spec 0278 D-11): for every allowance of the previous
 * year, creates or updates the one of the target year with
 * `carried_over_minutes` = the previous year's residual (possibly negative).
 * The annual allowance is copied only when the target row does not exist yet,
 * so an HR-set value is never overwritten (a null one is copied as null:
 * "use the type default"). Idempotent.
 *
 * Spec 0304 D-12: people with an employment profile who had no allowance row
 * in the previous year but rely on a type default also get the target row,
 * with a null `annual_minutes` and the previous year's residual as carry-over.
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

        // Step 3: people living on the type default alone, with no previous row
        return $count + $this->carryOverDefaults($year, $reference);
    }

    private function carryOverDefaults(int $year, CarbonImmutable $reference): int
    {
        $count = 0;
        $types = AbsenceType::query()
            ->where('is_active', true)
            ->where('deducts_balance', true)
            ->whereNotNull('default_annual_days')
            ->get();

        foreach ($types as $type) {
            User::query()
                ->where('is_active', true)
                ->whereHas('employment')
                ->whereNotIn('id', AbsenceAllowance::query()
                    ->select('user_id')
                    ->where('absence_type_id', $type->id)
                    ->where('year', $year - 1))
                ->chunkById(self::CHUNK, function ($users) use ($type, $year, $reference, &$count): void {
                    foreach ($users as $user) {
                        $this->write($user, $type, $year, $this->balances->row($user, $type, $year - 1, $reference)['residual_minutes'], null);
                        $count++;
                    }
                });
        }

        return $count;
    }

    private function writeFor(AbsenceAllowance $previous, int $year, CarbonImmutable $reference): void
    {
        $residual = $this->balances->row($previous->user, $previous->absenceType, $year - 1, $reference)['residual_minutes'];

        $this->write($previous->user, $previous->absenceType, $year, $residual, $previous->annual_minutes);
    }

    private function write(User $user, AbsenceType $type, int $year, int $carriedOver, ?int $annualMinutes): void
    {
        // Model save (not query update) so the activity log records it.
        $target = AbsenceAllowance::query()->firstOrNew([
            'user_id' => $user->id,
            'absence_type_id' => $type->id,
            'year' => $year,
        ]);

        if (! $target->exists) {
            $target->annual_minutes = $annualMinutes;
        }

        $target->carried_over_minutes = $carriedOver;
        $target->save();
    }
}

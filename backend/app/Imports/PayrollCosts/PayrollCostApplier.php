<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

use App\Models\PayrollCost;
use Illuminate\Support\Facades\DB;

/**
 * Writes a validated plan in one transaction (spec 0430, D-1, D-2, D-13): the
 * rows of the period are deleted through Eloquent, so the activity log keeps
 * the deletions, then the file rows are created.
 */
final class PayrollCostApplier
{
    public function apply(PayrollCostPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            // Step 1: drop the rows of the period being replaced
            PayrollCost::query()->where('period', $plan->period)->get()->each->delete();

            // Step 2: write the rows of the file
            foreach ($plan->entries as $entry) {
                PayrollCost::query()->create($entry);
            }
        });
    }
}

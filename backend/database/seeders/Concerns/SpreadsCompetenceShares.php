<?php

namespace Database\Seeders\Concerns;

use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;

/**
 * Spec 0346: a seeder that writes several "Ambito di assegnazione" rows
 * leaves them adding up to 100, as the user form demands — the split of D-3
 * (even, id order, remainder +1 on the first rows).
 */
trait SpreadsCompetenceShares
{
    private function spreadCompetenceShares(EmploymentProfile $employment): void
    {
        $lines = $employment->productLines()->orderBy('id')->get();

        if ($lines->isEmpty()) {
            return;
        }

        $base = intdiv(EmploymentProductLine::SHARE_TOTAL, $lines->count());
        $remainder = EmploymentProductLine::SHARE_TOTAL % $lines->count();

        foreach ($lines->values() as $index => $line) {
            $line->update(['share_percentage' => $base + ($index < $remainder ? 1 : 0)]);
        }

        $employment->unsetRelation('productLines');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\User;

/**
 * One "real" (computed) indicator of the report (spec 0106 data_contract).
 * The four constant-0 stub columns (aule_gestione/aule_partenza/
 * presa_appuntamenti/invio_presa_in_carico, D-5) have no implementation of
 * this contract — ReportBranchRowsBuilder emits their 0 directly, the same
 * default every non-applicable column gets too (D-15, rev-2).
 */
interface ReportIndicator
{
    /**
     * @param  array<int, int>  $categoryIds
     */
    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range): IndicatorResult;
}

<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Models\CommissionSettlementRun;
use App\Models\Company;
use App\Models\Referent;

/**
 * Builds the SettlementRunView of a run: used by the launch response and by
 * the later read of the same run.
 */
final class SettlementRunReader
{
    public function view(CommissionSettlementRun $run): SettlementRunView
    {
        $run->load('creator:id,name');
        $settlements = $run->settlements()
            ->orderBy('id')
            ->get(['id', 'commission_settlement_run_id', 'referent_id', 'company_id', 'taxable_amount', 'net_payable']);
        $skipped = collect($run->skipped ?? []);

        $referentIds = $settlements->pluck('referent_id')->merge($skipped->pluck('referent_id'))->unique()->values()->all();
        $companyIds = $settlements->pluck('company_id')->merge($skipped->pluck('company_id'))->unique()->values()->all();

        return new SettlementRunView(
            $run,
            $settlements,
            Referent::query()->whereIn('id', $referentIds)->pluck('name', 'id')->all(),
            Company::query()->whereIn('id', $companyIds)->pluck('denomination', 'id')->all(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionSettlementSkipReason;
use App\Enums\CommissionSettlementStatus;
use App\Enums\EnasarcoMandateType;
use App\Models\AgentFiscalProfile;
use App\Models\CommissionSettlement;
use App\Models\EnasarcoParameter;
use Carbon\CarbonInterface;

/**
 * Resolves the fiscal terms of a (referent, company) pair at the period end
 * and computes the settlement columns (spec 0213, D-2/D-3/D-4): the profile
 * valid at period_to, the ENASARCO parameters of its year and the ceiling
 * already consumed by the issued settlements. Writes nothing.
 */
final class SettlementComposer
{
    public function __construct(private readonly SettlementCalculator $calculator) {}

    /**
     * @param  int|null  $excludeSettlementId  the settlement being recalculated, left out of the used ceiling
     * @return array<string, mixed> columns of commission_settlements (snapshot + amounts)
     *
     * @throws SettlementSkippedException no valid profile or no ENASARCO parameters
     */
    public function compose(int $referentId, int $companyId, CarbonInterface $periodTo, MovementTotals $totals, ?int $excludeSettlementId = null): array
    {
        // Step 1: the profile valid at period_to
        $profile = $this->profileAt($referentId, $periodTo)
            ?? throw new SettlementSkippedException(CommissionSettlementSkipReason::MissingProfile);

        // Step 2: the ENASARCO parameters of the year, when the profile contributes
        $parameter = $profile->enasarco_enabled
            ? (EnasarcoParameter::query()->where('year', $periodTo->year)->first()
                ?? throw new SettlementSkippedException(CommissionSettlementSkipReason::MissingEnasarcoParameters))
            : null;

        // Step 3: amounts from the snapshot terms
        $terms = $this->terms($profile, $parameter);
        $used = $parameter === null ? '0.00' : $this->usedCeiling($referentId, $companyId, $periodTo->year, $excludeSettlementId);
        $amounts = $this->calculator->calculate($totals->accruals, $totals->adjustments, $terms, $used);

        return [
            'agent_fiscal_profile_id' => $profile->id,
            'vat_rate' => $terms->vatRate,
            'withholding_enabled' => $profile->withholding_enabled,
            'withholding_rate' => $profile->withholding_enabled ? $profile->withholding_rate : null,
            'withholding_base_percent' => $profile->withholding_enabled ? $profile->withholding_base_percent : null,
            'enasarco_enabled' => $profile->enasarco_enabled,
            'enasarco_mandate_type' => $profile->enasarco_enabled ? $profile->enasarco_mandate_type : null,
            ...$amounts->toAttributes(),
        ];
    }

    private function profileAt(int $referentId, CarbonInterface $date): ?AgentFiscalProfile
    {
        $day = $date->toDateString();

        return AgentFiscalProfile::query()
            ->with('vatRate:id,rate')
            ->where('referent_id', $referentId)
            ->whereDate('valid_from', '<=', $day)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $day))
            ->first();
    }

    private function terms(AgentFiscalProfile $profile, ?EnasarcoParameter $parameter): SettlementTerms
    {
        $ceiling = $parameter === null ? null : (string) ($profile->enasarco_mandate_type === EnasarcoMandateType::Mono
            ? $parameter->single_firm_ceiling
            : $parameter->multi_firm_ceiling);

        return new SettlementTerms(
            vatRate: $profile->vatRate === null ? null : (string) $profile->vatRate->rate,
            withholdingRate: $profile->withholding_enabled ? (string) $profile->withholding_rate : null,
            withholdingBasePercent: $profile->withholding_enabled ? (string) $profile->withholding_base_percent : null,
            enasarcoTotalRate: $parameter === null ? null : (string) $parameter->total_rate,
            enasarcoAgentSharePercent: $parameter === null ? null : (string) $parameter->agent_share_percent,
            enasarcoCeiling: $ceiling,
        );
    }

    /** ENASARCO base consumed in the year by the ISSUED settlements of the pair, the current one excluded. */
    private function usedCeiling(int $referentId, int $companyId, int $year, ?int $excludeSettlementId): string
    {
        return CommissionSettlement::query()
            ->where('referent_id', $referentId)
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('status', CommissionSettlementStatus::Issued->value)
            ->when($excludeSettlementId !== null, fn ($query) => $query->whereKeyNot($excludeSettlementId))
            ->get(['enasarco_base_amount'])
            ->reduce(fn (string $sum, CommissionSettlement $row): string => bcadd($sum, (string) $row->enasarco_base_amount, 2), '0.00');
    }
}

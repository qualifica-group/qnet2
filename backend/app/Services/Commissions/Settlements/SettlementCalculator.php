<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

/**
 * Pure bcmath arithmetic of a settlement (spec 0213, D-2/D-3): no database, no
 * float. Every line is rounded half away from zero to 2 decimals, like
 * CommissionShareCalculator, then the lines are combined.
 */
final class SettlementCalculator
{
    private const int SCALE = 2;

    private const int WORKING_SCALE = 8;

    private const string PERCENT = '100';

    /**
     * @param  string  $enasarcoUsed  ENASARCO base already consumed this year by the issued settlements of the pair
     */
    public function calculate(string $accruals, string $adjustments, SettlementTerms $terms, string $enasarcoUsed = '0.00'): SettlementAmounts
    {
        // Step 1: the taxable amount is the net of accruals, reversals and adjustments
        $taxable = bcadd($accruals, $adjustments, self::SCALE);

        // Step 2: VAT and withholding on the taxable amount
        $vat = $terms->vatRate === null ? '0.00' : $this->percentOf($taxable, $terms->vatRate);
        $withholding = $terms->hasWithholding()
            ? $this->round(bcdiv(bcmul($this->percentOf($taxable, $terms->withholdingBasePercent, self::WORKING_SCALE), $terms->withholdingRate, self::WORKING_SCALE), self::PERCENT, self::WORKING_SCALE))
            : '0.00';

        // Step 3: ENASARCO, capped by what is left of the yearly ceiling
        [$enasarcoBase, $enasarcoAgent, $enasarcoPrincipal] = $terms->hasEnasarco()
            ? $this->enasarco($taxable, $terms, $enasarcoUsed)
            : ['0.00', '0.00', '0.00'];

        // Step 4: net payable to the agent
        $net = bcsub(bcsub(bcadd($taxable, $vat, self::SCALE), $withholding, self::SCALE), $enasarcoAgent, self::SCALE);

        return new SettlementAmounts(
            bcadd($accruals, '0', self::SCALE), bcadd($adjustments, '0', self::SCALE), $taxable,
            $vat, $withholding, $enasarcoBase, $enasarcoAgent, $enasarcoPrincipal, $net,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string} base, agent share, principal share
     */
    private function enasarco(string $taxable, SettlementTerms $terms, string $used): array
    {
        $room = bcsub((string) $terms->enasarcoCeiling, $used, self::SCALE);
        $base = bccomp($room, '0', self::SCALE) <= 0 || bccomp($taxable, '0', self::SCALE) <= 0
            ? '0.00'
            : (bccomp($taxable, $room, self::SCALE) < 0 ? $taxable : $room);

        $total = $this->percentOf($base, (string) $terms->enasarcoTotalRate);
        $agent = $this->percentOf($total, (string) $terms->enasarcoAgentSharePercent);

        return [$base, $agent, bcsub($total, $agent, self::SCALE)];
    }

    /** `$amount` x `$percent` / 100, rounded to 2 decimals (or kept at `$scale` when given). */
    private function percentOf(string $amount, string $percent, int $scale = self::SCALE): string
    {
        $value = bcdiv(bcmul($amount, $percent, self::WORKING_SCALE), self::PERCENT, self::WORKING_SCALE);

        return $scale === self::SCALE ? $this->round($value) : $value;
    }

    private function round(string $value): string
    {
        $half = bccomp($value, '0', self::WORKING_SCALE) < 0 ? '-0.005' : '0.005';

        return bcadd($value, $half, self::SCALE);
    }
}

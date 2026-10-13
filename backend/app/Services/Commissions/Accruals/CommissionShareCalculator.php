<?php

declare(strict_types=1);

namespace App\Services\Commissions\Accruals;

/**
 * Pure bcmath arithmetic of the commission accruals (spec 0212, D-1): no
 * database, no float. Amounts are rounded half away from zero to 2 decimals,
 * like InvoiceAmountCalculator.
 */
final class CommissionShareCalculator
{
    private const int SCALE = 2;

    private const int WORKING_SCALE = 8;

    /**
     * The share of `$calculated` earned by each invoice of one quote line: the
     * difference of the rounded cumulative amounts, so the shares add up to the
     * calculated amount exactly and never exceed it (cap at 100%).
     *
     * @param  array<int, string>  $invoiceNets  invoice_id => net amount of the line, ordered by (document_date, id)
     * @return array<int, string> invoice_id => share
     */
    public function shares(string $calculated, string $lineNet, array $invoiceNets): array
    {
        $shares = [];
        $previous = '0.00';
        $cumulative = '0.00';

        foreach ($invoiceNets as $invoiceId => $net) {
            $cumulative = bcadd($cumulative, $net, self::SCALE);
            $earned = $this->earnedAt($calculated, $lineNet, $cumulative);
            $shares[$invoiceId] = bcsub($earned, $previous, self::SCALE);
            $previous = $earned;
        }

        return $shares;
    }

    /** The accrued amount when `$collected` of `$total` was cashed: `$share` x min(1, collected / total). */
    public function expected(string $share, string $collected, string $total): string
    {
        if (bccomp($total, '0', self::SCALE) <= 0 || bccomp($collected, '0', self::SCALE) <= 0) {
            return '0.00';
        }

        if (bccomp($collected, $total, self::SCALE) >= 0) {
            return bcadd($share, '0', self::SCALE);
        }

        return $this->round(bcdiv(bcmul($share, $collected, self::WORKING_SCALE), $total, self::WORKING_SCALE));
    }

    /** Cumulative amount earned once `$cumulativeNet` of the line has been invoiced. */
    private function earnedAt(string $calculated, string $lineNet, string $cumulativeNet): string
    {
        // A free line has no ratio: the whole share lands on the first invoice that carries it
        if (bccomp($lineNet, '0', self::SCALE) <= 0) {
            return bcadd($calculated, '0', self::SCALE);
        }

        if (bccomp($cumulativeNet, '0', self::SCALE) <= 0) {
            return '0.00';
        }

        if (bccomp($cumulativeNet, $lineNet, self::SCALE) >= 0) {
            return bcadd($calculated, '0', self::SCALE);
        }

        return $this->round(bcdiv(bcmul($calculated, $cumulativeNet, self::WORKING_SCALE), $lineNet, self::WORKING_SCALE));
    }

    private function round(string $value): string
    {
        $half = bccomp($value, '0', self::WORKING_SCALE) < 0 ? '-0.005' : '0.005';

        return bcadd($value, $half, self::SCALE);
    }
}

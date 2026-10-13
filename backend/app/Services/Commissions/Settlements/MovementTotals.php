<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionAccrualType;
use App\Models\CommissionAccrual;
use Illuminate\Support\Collection;

/**
 * Sums of the movements of one settlement (spec 0213, D-2): the invoice-driven
 * ones (ACCRUAL/REVERSAL) apart from the manual adjustments.
 */
final readonly class MovementTotals
{
    public function __construct(public string $accruals, public string $adjustments, public string $taxable) {}

    /**
     * @param  Collection<int, CommissionAccrual>  $movements
     */
    public static function from(Collection $movements): self
    {
        $accruals = '0.00';
        $adjustments = '0.00';

        foreach ($movements as $movement) {
            if ($movement->type === CommissionAccrualType::Adjustment) {
                $adjustments = bcadd($adjustments, (string) $movement->amount, 2);
            } else {
                $accruals = bcadd($accruals, (string) $movement->amount, 2);
            }
        }

        return new self($accruals, $adjustments, bcadd($accruals, $adjustments, 2));
    }
}

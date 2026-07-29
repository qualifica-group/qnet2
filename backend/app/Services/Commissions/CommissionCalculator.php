<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\CommissionCalculationInput;
use App\Enums\CommissionType;

final class CommissionCalculator
{
    public function calculate(CommissionCalculationInput $input): string
    {
        $amount = $input->type === CommissionType::Percentage
            ? ((float) $input->lineNetAmount * (float) $input->value) / 100
            : (float) $input->value;

        return number_format(round($amount, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }
}

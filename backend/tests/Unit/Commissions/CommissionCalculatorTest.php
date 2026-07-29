<?php

use App\DataObjects\Commissions\CommissionCalculationInput;
use App\Enums\CommissionType;
use App\Services\Commissions\CommissionCalculator;

it('calculates percentages on net amount and fixed amounts once per line', function () {
    $calculator = new CommissionCalculator;

    expect($calculator->calculate(new CommissionCalculationInput(
        type: CommissionType::Percentage,
        value: '7.5000',
        lineNetAmount: '333.33',
    )))->toBe('25.00')
        ->and($calculator->calculate(new CommissionCalculationInput(
            type: CommissionType::FixedAmount,
            value: '14.2370',
            lineNetAmount: '999.99',
        )))->toBe('14.24');
});

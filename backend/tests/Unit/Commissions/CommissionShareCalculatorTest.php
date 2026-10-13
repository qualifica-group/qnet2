<?php

use App\Services\Commissions\Accruals\CommissionShareCalculator;

it('splits a commission by the cumulative invoiced ratio and sums exactly to it', function () {
    $shares = (new CommissionShareCalculator)->shares('33.33', '1000.00', [1 => '333.33', 2 => '333.33', 3 => '333.34']);

    expect($shares)->toBe([1 => '11.11', 2 => '11.11', 3 => '11.11'])
        ->and(array_reduce($shares, fn (string $sum, string $share): string => bcadd($sum, $share, 2), '0.00'))->toBe('33.33');
});

it('caps the total at 100% and gives nothing to an invoice beyond it', function () {
    $shares = (new CommissionShareCalculator)->shares('100.00', '1000.00', [1 => '400.00', 2 => '600.00', 3 => '200.00']);

    expect($shares)->toBe([1 => '40.00', 2 => '60.00', 3 => '0.00']);
});

it('gives the whole share to the first invoice when the quote line is free', function () {
    expect((new CommissionShareCalculator)->shares('50.00', '0.00', [7 => '10.00', 9 => '5.00']))->toBe([7 => '50.00', 9 => '0.00']);
});

it('clamps a negative cumulative amount to zero', function () {
    expect((new CommissionShareCalculator)->shares('100.00', '1000.00', [1 => '-50.00', 2 => '500.00']))->toBe([1 => '0.00', 2 => '45.00']);
});

it('applies the collected ratio half-up and returns the exact share when fully collected', function () {
    $calculator = new CommissionShareCalculator;

    expect($calculator->expected('33.33', '610.00', '1220.00'))->toBe('16.67')
        ->and($calculator->expected('33.33', '1220.00', '1220.00'))->toBe('33.33')
        ->and($calculator->expected('33.33', '1300.00', '1220.00'))->toBe('33.33')
        ->and($calculator->expected('33.33', '10.00', '0.00'))->toBe('0.00')
        ->and($calculator->expected('33.33', '0.00', '1220.00'))->toBe('0.00');
});

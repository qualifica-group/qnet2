<?php

use App\Services\Commissions\Settlements\DocumentToleranceChecker;

function toleranceReference(): array
{
    return ['taxable_amount' => '1000.00', 'vat_amount' => '220.00', 'withholding_amount' => '115.00', 'enasarco_amount' => '85.00', 'total_amount' => '1220.00'];
}

it('AC-001: a difference equal to the tolerance passes on every amount', function () {
    $declared = ['taxable_amount' => '1001.00', 'vat_amount' => '219.00', 'withholding_amount' => '116.00', 'enasarco_amount' => '84.00', 'total_amount' => '1221.00'];

    expect((new DocumentToleranceChecker)->violations($declared, toleranceReference(), '1.00'))->toBe([]);
});

it('AC-001: 1.01 on a single amount fails and names that amount only', function (string $amount, string $declaredValue) {
    $declared = [...toleranceReference(), $amount => $declaredValue];

    $violations = (new DocumentToleranceChecker)->violations($declared, toleranceReference(), '1.00');

    expect(array_keys($violations))->toBe(['document_'.$amount])
        ->and($violations['document_'.$amount])->toBe(['difference' => '1.01', 'tolerance' => '1.00']);
})->with([
    'taxable' => ['taxable_amount', '1001.01'],
    'vat' => ['vat_amount', '218.99'],
    'withholding' => ['withholding_amount', '116.01'],
    'enasarco' => ['enasarco_amount', '83.99'],
    'total' => ['total_amount', '1221.01'],
]);

it('AC-001: a tolerance of 0.00 accepts only identical amounts', function () {
    $checker = new DocumentToleranceChecker;
    $tolerance = '0.00';

    expect($checker->violations(toleranceReference(), toleranceReference(), $tolerance))->toBe([])
        ->and(array_keys($checker->violations([...toleranceReference(), 'vat_amount' => '220.01'], toleranceReference(), $tolerance)))->toBe(['document_vat_amount']);
});

it('AC-001: the differences are signed, document minus settlement', function () {
    $declared = [...toleranceReference(), 'vat_amount' => '219.50', 'taxable_amount' => '1000.25'];

    expect((new DocumentToleranceChecker)->differences($declared, toleranceReference()))->toBe([
        'taxable_amount' => '0.25', 'vat_amount' => '-0.50', 'withholding_amount' => '0.00', 'enasarco_amount' => '0.00', 'total_amount' => '0.00',
    ]);
});

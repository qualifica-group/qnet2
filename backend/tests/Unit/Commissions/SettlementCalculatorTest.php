<?php

use App\Services\Commissions\Settlements\SettlementCalculator;
use App\Services\Commissions\Settlements\SettlementTerms;

function settlementTerms(array $overrides = []): SettlementTerms
{
    return new SettlementTerms(...[
        'vatRate' => '22.00',
        'withholdingRate' => '23.00',
        'withholdingBasePercent' => '50.00',
        'enasarcoTotalRate' => '17.00',
        'enasarcoAgentSharePercent' => '50.00',
        'enasarcoCeiling' => '10000.00',
        ...$overrides,
    ]);
}

it('AC-001: computes VAT, withholding, ENASARCO and the net payable', function () {
    $amounts = (new SettlementCalculator)->calculate('900.00', '100.00', settlementTerms());

    expect($amounts->taxable)->toBe('1000.00')
        ->and($amounts->vat)->toBe('220.00')
        ->and($amounts->withholding)->toBe('115.00')
        ->and($amounts->enasarcoBase)->toBe('1000.00')
        ->and($amounts->enasarcoAgent)->toBe('85.00')
        ->and($amounts->enasarcoPrincipal)->toBe('85.00')
        ->and($amounts->netPayable)->toBe('1020.00')
        ->and($amounts->accruals)->toBe('900.00')
        ->and($amounts->adjustments)->toBe('100.00');
});

it('AC-001: without VAT, withholding and ENASARCO the net equals the taxable amount', function () {
    $amounts = (new SettlementCalculator)->calculate('1000.00', '0.00', new SettlementTerms);

    expect($amounts->vat)->toBe('0.00')
        ->and($amounts->withholding)->toBe('0.00')
        ->and($amounts->enasarcoBase)->toBe('0.00')
        ->and($amounts->enasarcoAgent)->toBe('0.00')
        ->and($amounts->enasarcoPrincipal)->toBe('0.00')
        ->and($amounts->netPayable)->toBe('1000.00');
});

it('AC-002: the ENASARCO base is capped by what is left of the ceiling', function (string $used, string $base) {
    $amounts = (new SettlementCalculator)->calculate('1000.00', '0.00', settlementTerms(), $used);

    expect($amounts->enasarcoBase)->toBe($base);
})->with([
    'room for all' => ['0.00', '1000.00'],
    'room for half' => ['9500.00', '500.00'],
    'ceiling reached' => ['10000.00', '0.00'],
    'ceiling exceeded' => ['12000.00', '0.00'],
]);

it('rounds every line half away from zero', function () {
    $terms = settlementTerms(['vatRate' => '22.00', 'withholdingRate' => '23.00', 'withholdingBasePercent' => '50.00', 'enasarcoTotalRate' => '17.00', 'enasarcoAgentSharePercent' => '50.00']);
    $amounts = (new SettlementCalculator)->calculate('10.03', '0.00', $terms);

    // 10.03 x 22% = 2.2066 ; x 50% x 23% = 1.153450 ; 17% = 1.7051 -> 1.71 ; agent 0.855 -> 0.86 ; principal 1.71 - 0.86
    expect($amounts->vat)->toBe('2.21')
        ->and($amounts->withholding)->toBe('1.15')
        ->and($amounts->enasarcoAgent)->toBe('0.86')
        ->and($amounts->enasarcoPrincipal)->toBe('0.85');
});

it('keeps a negative balance negative and never charges ENASARCO on it', function () {
    $amounts = (new SettlementCalculator)->calculate('-100.00', '0.00', settlementTerms());

    expect($amounts->taxable)->toBe('-100.00')
        ->and($amounts->vat)->toBe('-22.00')
        ->and($amounts->enasarcoBase)->toBe('0.00')
        ->and($amounts->enasarcoAgent)->toBe('0.00');
});

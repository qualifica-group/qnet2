<?php

use App\Services\AiAssistant\Costs\AiCallUsage;
use App\Services\AiAssistant\Costs\AiMoney;

// A cost the provider reports below zero or not as a number is as good as missing: the ledger then charges the reservation.
it('reads a valid cost as a decimal string', function (mixed $cost, string $expected) {
    expect(AiCallUsage::fromOpenRouter('m', null, ['cost' => $cost])->costUsd)->toBe($expected);
})->with([
    'float' => [0.0012, '0.001200000000'],
    'string' => ['0.5', '0.500000000000'],
    'zero' => [0, '0.000000000000'],
]);

it('treats a negative or non numeric cost as missing', function (mixed $cost) {
    expect(AiCallUsage::fromOpenRouter('m', null, ['cost' => $cost])->costUsd)->toBeNull();
})->with([
    'negative float' => [-0.5],
    'negative string' => ['-3'],
    'negative zero-ish' => [-1.0E-9],
    'text' => ['free'],
    'infinite' => [INF],
    'nan' => [NAN],
    'huge exponent' => ['1e999'],
    'array' => [[1]],
    'null' => [null],
]);

it('gives no amount for a value below zero or not a finite number', function () {
    expect(AiMoney::fromNonNegative(-4))->toBeNull()
        ->and(AiMoney::fromNonNegative('abc'))->toBeNull()
        ->and(AiMoney::fromNonNegative('2.5'))->toBe('2.500000000000');
});

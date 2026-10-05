<?php

use App\Rules\ValidCardNumber;

// Spec 0189, AC-006: 12-19 digits and a valid Luhn checksum.

it('accepts Luhn-valid numbers of 12 to 19 digits', function (string $number) {
    expect(ValidCardNumber::isValid($number))->toBeTrue();
})->with([
    'visa' => '4111111111111111',
    'mastercard' => '5555555555554444',
    'amex 15' => '378282246310005',
]);

it('rejects bad checksum, non digits and out-of-range length', function (string $number) {
    expect(ValidCardNumber::isValid($number))->toBeFalse();
})->with([
    'bad luhn' => '4111111111111112',
    'letters' => '4111abcd11111111',
    'too short' => '41111111111',
    'too long' => '41111111111111111115',
]);

it('strips spaces and dashes when normalizing', function () {
    expect(ValidCardNumber::normalize('4111 1111-1111 1111'))->toBe('4111111111111111');
});

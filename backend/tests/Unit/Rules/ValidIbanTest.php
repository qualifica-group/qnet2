<?php

use App\Rules\ValidIban;

// Spec 0189, D-5: ISO 13616 structure + mod-97, any country.

it('accepts valid IBANs of several countries', function (string $iban) {
    expect(ValidIban::isValid($iban))->toBeTrue();
})->with([
    'IT' => 'IT60X0542811101000000123456',
    'DE' => 'DE89370400440532013000',
    'GB' => 'GB82WEST12345698765432',
    'FR' => 'FR1420041010050500013M02606',
]);

it('rejects a wrong checksum, a bad structure and a wrong length', function (string $iban) {
    expect(ValidIban::isValid($iban))->toBeFalse();
})->with([
    'altered digit' => 'IT60X0542811101000000123457',
    'garbage' => 'XYZ',
    'too short' => 'IT60X054281',
    'no country' => '1260X0542811101000000123456',
    'too long' => 'IT60X05428111010000001234560000000000',
]);

it('normalizes to uppercase without spaces or dashes', function () {
    expect(ValidIban::normalize('it60 x054-2811 1010 0000 0123 456'))->toBe('IT60X0542811101000000123456');
});

it('passes a blank value (required-ness is the FormRequest call)', function () {
    $failures = [];

    (new ValidIban)->validate('iban', '', function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toBe([]);
});

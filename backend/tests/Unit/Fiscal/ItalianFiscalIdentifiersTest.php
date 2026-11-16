<?php

use App\Support\Fiscal\ForeignFiscalCode;
use App\Support\Fiscal\ItalianTaxCode;
use App\Support\Fiscal\ItalianVatNumber;
use App\Support\Fiscal\TaxCodeNameEncoder;

// Pure algorithms: no database, no framework binding needed.

// ---------------------------------------------------------------------------
// Tax code — format and control character
// ---------------------------------------------------------------------------

it('accepts a tax code with the right control character', function () {
    expect(ItalianTaxCode::isValid('RSSMRA80A01H501U'))->toBeTrue();
});

it('accepts a tax code typed with spaces and lowercase', function () {
    expect(ItalianTaxCode::isValid(' rss mra80a01h501u '))->toBeTrue();
});

it('rejects a tax code whose control character is wrong', function () {
    expect(ItalianTaxCode::isValid('RSSMRA80A01H501W'))->toBeFalse();
});

it('rejects a tax code of the wrong length or shape', function (string $code) {
    expect(ItalianTaxCode::isValid($code))->toBeFalse();
})->with([
    'too short' => 'RSSMRA80A01H501',
    'too long' => 'RSSMRA80A01H501UU',
    'invalid month letter' => 'RSSMRA80Z01H501U',
    'digits where letters belong' => '123MRA80A01H501U',
    'empty' => '',
]);

// ---------------------------------------------------------------------------
// Tax code — decoding
// ---------------------------------------------------------------------------

it('decodes the encoded birth date', function () {
    expect(ItalianTaxCode::birthDate('RSSMRA80A01H501U'))
        ->toBe(['year' => 80, 'month' => 1, 'day' => 1]);
});

it('decodes a female birth day, stripping the +40 offset', function () {
    expect(ItalianTaxCode::isFemale('BNCLRA85M45F205P'))->toBeTrue();
    expect(ItalianTaxCode::birthDate('BNCLRA85M45F205P'))
        ->toBe(['year' => 85, 'month' => 8, 'day' => 5]);
});

it('reads an omocodia-corrected code as its plain digits', function () {
    // Same person as RSSMRA80A01H501U with the year digits substituted.
    expect(ItalianTaxCode::withoutOmocodia('RSSMRAU0A01H501U'))
        ->toBe('RSSMRA80A01H501U');
});

it('exposes the surname and name triples the code carries', function () {
    expect(ItalianTaxCode::surnameTriple('RSSMRA80A01H501U'))->toBe('RSS');
    expect(ItalianTaxCode::nameTriple('RSSMRA80A01H501U'))->toBe('MRA');
});

// ---------------------------------------------------------------------------
// Name encoding
// ---------------------------------------------------------------------------

it('encodes a surname as consonants then vowels', function (string $surname, string $expected) {
    expect(TaxCodeNameEncoder::surname($surname))->toBe($expected);
})->with([
    ['Rossi', 'RSS'],
    ['Bianchi', 'BNC'],
    ['Fo', 'FOX'],
    ["D'Amico", 'DMC'],
    ['Nicolò', 'NCL'],
]);

it('drops the second consonant of a name with four or more', function () {
    expect(TaxCodeNameEncoder::name('Giuseppe'))->toBe('GPP');
});

it('falls back to the surname rule for a name with fewer than four consonants', function () {
    expect(TaxCodeNameEncoder::name('Mario'))->toBe('MRA');
    expect(TaxCodeNameEncoder::name('Ida'))->toBe('DIA');
});

// ---------------------------------------------------------------------------
// VAT number
// ---------------------------------------------------------------------------

it('accepts a VAT number with a valid control digit', function () {
    expect(ItalianVatNumber::isValid('00743110157'))->toBeTrue();
});

it('accepts a VAT number carrying the IT prefix or separators', function () {
    expect(ItalianVatNumber::isValid('IT 00743110157'))->toBeTrue();
});

it('rejects an invalid VAT number', function (string $value) {
    expect(ItalianVatNumber::isValid($value))->toBeFalse();
})->with([
    'wrong control digit' => '00743110158',
    'too short' => '0074311015',
    'letters' => '0074311015A',
    'all zeros' => '00000000000',
    'empty' => '',
]);

// ---------------------------------------------------------------------------
// Foreign code
// ---------------------------------------------------------------------------

it('accepts a foreign code written with its country prefix', function (string $value) {
    expect(ForeignFiscalCode::isValid($value))->toBeTrue();
})->with([
    'German VAT' => 'DE123456789',
    'French VAT with spaces' => 'fr 12 345678901',
    'Swiss UID' => 'CHE-123.456.789',
    'Spanish NIF' => 'ES12345678Z',
    'Greek VIES prefix' => 'EL123456789',
]);

it('rejects a value that is not a prefixed foreign code', function (string $value) {
    expect(ForeignFiscalCode::isValid($value))->toBeFalse();
})->with([
    'no prefix' => '123456789',
    'Italian prefix' => 'IT00743110157',
    'Italian personal code shape' => 'RSSMRA80A01H501',
    'too short' => 'DE1',
    'too long' => 'DE12345678901234567',
    'empty' => '',
]);

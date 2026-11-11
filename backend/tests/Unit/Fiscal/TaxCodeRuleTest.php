<?php

use App\Rules\TaxCode;
use App\Rules\VatNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// The Validator facade needs the container, so bind the full TestCase
// explicitly (the default Pest binding only applies to the Feature suite).
uses(TestCase::class);

/**
 * The rules are exercised through the validator exactly as the FormRequests
 * use them, prefix included — DataAwareRule only receives the payload that way.
 *
 * @param  array<string, mixed>  $payload
 */
function taxCodeErrors(array $payload, string $prefix = ''): array
{
    return Validator::make(
        $payload,
        [$prefix.'tax_code' => ['nullable', 'string', new TaxCode($prefix)]],
    )->errors()->get($prefix.'tax_code');
}

// ---------------------------------------------------------------------------
// Individual card
// ---------------------------------------------------------------------------

it('passes a tax code consistent with the whole card', function () {
    expect(taxCodeErrors([
        'type' => 'individual',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'birth_date' => '1980-01-01',
        'gender' => 'male',
        'tax_code' => 'RSSMRA80A01H501U',
    ]))->toBeEmpty();
});

it('rejects a tax code whose control character is wrong', function () {
    expect(taxCodeErrors(['type' => 'individual', 'tax_code' => 'RSSMRA80A01H501W']))
        ->toContain('The tax code is not valid. A foreign one needs its country prefix (e.g. DE12345678901).');
});

it('rejects a tax code that does not match the last name', function () {
    expect(taxCodeErrors([
        'type' => 'individual',
        'last_name' => 'Bianchi',
        'tax_code' => 'RSSMRA80A01H501U',
    ]))->toContain('The tax code does not match the last name.');
});

it('rejects a tax code that does not match the first name', function () {
    expect(taxCodeErrors([
        'type' => 'individual',
        'last_name' => 'Rossi',
        'first_name' => 'Luigi',
        'tax_code' => 'RSSMRA80A01H501U',
    ]))->toContain('The tax code does not match the first name.');
});

it('rejects a tax code that does not match the birth date', function () {
    expect(taxCodeErrors([
        'type' => 'individual',
        'birth_date' => '1980-02-01',
        'tax_code' => 'RSSMRA80A01H501U',
    ]))->toContain('The tax code does not match the birth date.');
});

it('accepts a birth date of another century, the code carrying no century', function () {
    expect(taxCodeErrors([
        'type' => 'individual',
        'birth_date' => '1880-01-01',
        'tax_code' => 'RSSMRA80A01H501U',
    ]))->toBeEmpty();
});

it('rejects a tax code that does not match the gender', function () {
    expect(taxCodeErrors([
        'type' => 'individual',
        'gender' => 'female',
        'tax_code' => 'RSSMRA80A01H501U',
    ]))->toContain('The tax code does not match the gender.');
});

it('checks only the anagraphic fields present in a sparse payload', function () {
    expect(taxCodeErrors(['type' => 'individual', 'tax_code' => 'RSSMRA80A01H501U']))
        ->toBeEmpty();
});

it('accepts a foreign code on an individual without the consistency checks', function () {
    expect(taxCodeErrors([
        'type' => 'individual',
        'first_name' => 'Hans',
        'last_name' => 'Müller',
        'birth_date' => '1980-01-01',
        'tax_code' => 'DE12345678901',
    ]))->toBeEmpty();
});

it('still rejects a mistyped Italian code that starts like a country prefix', function () {
    expect(taxCodeErrors(['type' => 'individual', 'tax_code' => 'RSSMRA80A01H501']))
        ->toContain('The tax code is not valid. A foreign one needs its country prefix (e.g. DE12345678901).');
});

it('accepts a blank tax code: the field stays optional', function () {
    expect(taxCodeErrors(['type' => 'individual', 'tax_code' => '']))->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Company card
// ---------------------------------------------------------------------------

it('requires the 11-digit numeric code on a company card', function () {
    expect(taxCodeErrors(['type' => 'company', 'tax_code' => '00743110157']))
        ->toBeEmpty();

    expect(taxCodeErrors(['type' => 'company', 'tax_code' => 'FR12345678901']))
        ->toBeEmpty();

    expect(taxCodeErrors(['type' => 'company', 'tax_code' => 'RSSMRA80A01H501U']))
        ->toContain('The tax code of a company must be 11 digits with a valid control digit, or a foreign code with its country prefix.');
});

// ---------------------------------------------------------------------------
// Prefixed payloads and the type-less (inline editing) payload
// ---------------------------------------------------------------------------

it('reads the sibling fields under the payload prefix', function () {
    expect(taxCodeErrors([
        'personal_data' => [
            'type' => 'individual',
            'last_name' => 'Bianchi',
            'tax_code' => 'RSSMRA80A01H501U',
        ],
    ], 'personal_data.'))->toContain('The tax code does not match the last name.');
});

it('accepts either shape when the payload carries no card type', function () {
    expect(taxCodeErrors(['tax_code' => 'RSSMRA80A01H501U']))->toBeEmpty();
    expect(taxCodeErrors(['tax_code' => '00743110157']))->toBeEmpty();
    expect(taxCodeErrors(['tax_code' => 'RSSMRA80A01H501W']))
        ->toContain('The tax code is not valid. A foreign one needs its country prefix (e.g. DE12345678901).');
});

// ---------------------------------------------------------------------------
// VAT number
// ---------------------------------------------------------------------------

it('validates a VAT number independently of the card type', function () {
    $errors = fn (string $value) => Validator::make(
        ['vat_number' => $value],
        ['vat_number' => ['nullable', 'string', new VatNumber]],
    )->errors()->get('vat_number');

    expect($errors('00743110157'))->toBeEmpty();
    expect($errors(''))->toBeEmpty();
    expect($errors('00743110158'))->toContain('The VAT number is not valid. A foreign one needs its country prefix (e.g. DE123456789).');
});

it('accepts a foreign VAT number written with its country prefix', function () {
    $errors = fn (string $value) => Validator::make(
        ['vat_number' => $value],
        ['vat_number' => ['nullable', 'string', new VatNumber]],
    )->errors()->get('vat_number');

    expect($errors('DE 123456789'))->toBeEmpty();
    expect($errors('CHE-123.456.789'))->toBeEmpty();
    expect($errors('123456789'))->not->toBeEmpty();
});

<?php

use App\Enums\ContactTypeEnum;
use App\Support\InputFormat;

// Pure formatter: no database, no framework binding needed. Mirrored by the
// frontend twin `src/lib/formatting/input-format.test.ts` — same cases.

// ---------------------------------------------------------------------------
// Phone — digits only, no country code ever assumed (user choice 2026-07-23)
// ---------------------------------------------------------------------------

it('collapses every spelling of the same national number onto one', function (string $typed) {
    expect(InputFormat::phone($typed))->toBe('3331234567');
})->with([
    '333 12 34 567',
    '333-1234567',
    '(333) 1234567',
    ' 333.123.4567 ',
    '333/1234567',
]);

it('keeps an international prefix as a leading plus', function (string $typed) {
    expect(InputFormat::phone($typed))->toBe('+393331234567');
})->with([
    '+39 333 1234567',
    '+39-333-1234567',
    '0039 333 1234567',
    '(0039) 3331234567',
]);

it('never invents a country prefix that was not typed', function () {
    expect(InputFormat::phone('02 1234567'))->toBe('021234567');
});

it('drops a plus that is not in first position', function () {
    expect(InputFormat::phone('333+444'))->toBe('333444');
});

// ---------------------------------------------------------------------------
// Person name — title case with the apostrophe exception
// ---------------------------------------------------------------------------

it('title-cases a person name whatever case it was typed in', function (string $typed, string $expected) {
    expect(InputFormat::personName($typed))->toBe($expected);
})->with([
    ['  mario   rossi ', 'Mario Rossi'],
    ['MARIO ROSSI', 'Mario Rossi'],
    ['MaRiO rOsSi', 'Mario Rossi'],
    ['de luca', 'De Luca'],
    ['di maria rossi', 'Di Maria Rossi'],
    ['anna-maria', 'Anna-Maria'],
    ['josé', 'José'],
]);

it('uppercases the letter after an apostrophe', function (string $typed, string $expected) {
    expect(InputFormat::personName($typed))->toBe($expected);
})->with([
    ["d'angelo", "D'Angelo"],
    ["DELL'ACQUA", "Dell'Acqua"],
    ['o’connor', 'O’Connor'],
]);

it('leaves a company name case alone and only collapses its spacing', function () {
    expect(InputFormat::plainText('  ACME   S.R.L. '))->toBe('ACME S.R.L.');
});

// ---------------------------------------------------------------------------
// Fiscal identifiers
// ---------------------------------------------------------------------------

it('uppercases a tax code and strips its separators', function () {
    expect(InputFormat::taxCode(' rss mra80a01h501u '))->toBe('RSSMRA80A01H501U');
});

it('strips the optional IT prefix from a VAT number', function () {
    expect(InputFormat::vatNumber('IT 12345678903'))->toBe('12345678903');
});

it('uppercases an SDI code and strips its separators', function () {
    expect(InputFormat::sdiCode(' abc-1234 '))->toBe('ABC1234');
});

// ---------------------------------------------------------------------------
// Contact value — dispatched on the channel
// ---------------------------------------------------------------------------

it('formats a contact value per its channel', function (ContactTypeEnum $type, string $typed, string $expected) {
    expect(InputFormat::contactValue($type, $typed))->toBe($expected);
})->with([
    [ContactTypeEnum::Phone, '333 12 34 567', '3331234567'],
    [ContactTypeEnum::Fax, '02 / 1234567', '021234567'],
    [ContactTypeEnum::Email, '  Mario.Rossi@Example.COM ', 'mario.rossi@example.com'],
    [ContactTypeEnum::Pec, ' MARIO@PEC.IT', 'mario@pec.it'],
    // A URL path IS case-sensitive: only the surrounding blanks go.
    [ContactTypeEnum::Website, ' https://Example.com/Path ', 'https://Example.com/Path'],
]);

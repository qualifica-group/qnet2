<?php

use App\Imports\ImportRowContext;
use App\Imports\Recognition\PersonNameRecognizer;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

// ---------------------------------------------------------------------------
// Spec 0138 — PersonNameRecognizer: ordinary characters in person names
// ---------------------------------------------------------------------------

function personNameContext(): ImportRowContext
{
    return new ImportRowContext(1, User::factory()->make());
}

it('AC-001: cleans styled letters, symbols, separators and foreign letters', function (string $raw, string $expected) {
    expect(PersonNameRecognizer::clean($raw))->toBe($expected);
})->with([
    'script letters' => ["\u{1D4DC}\u{1D4EE}\u{1D4FB}\u{1D502}.", 'Mery'],
    'sans-serif bold' => ["\u{1D5E6}\u{1D5EE}\u{1D5FA}\u{1D602}\u{1D5F2}\u{1D5F9}\u{1D5F2} \u{1D5E4}\u{1D602}\u{1D5EE}\u{1D5FF}\u{1D5F2}\u{1D600}\u{1D5F6}\u{1D5FA}\u{1D5EE}", 'Samuele Quaresima'],
    'heart symbol' => ["Deny\u{2661}", 'Deny'],
    'underscore' => ['Martina_Lipari', 'Martina Lipari'],
    'underscores and digits' => ['Vale__92', 'Vale'],
    'pipes' => ['|Chiara Simonte|', 'Chiara Simonte'],
    'dots and hieroglyphs' => ["Julie J.L. \u{131A9}\u{131AA} \u{13086}", 'Julie J L'],
    'cyrillic' => ['Марио', 'Mario'],
    'apostrophe' => ["D'Angelo", "D'Angelo"],
    'typographic apostrophe' => ["D\u{2019}Angelo", "D\u{2019}Angelo"],
    'hyphen' => ['Anna-Maria', 'Anna-Maria'],
]);

it('AC-002: keeps accented Latin letters untouched and unflagged', function (string $name) {
    $result = (new PersonNameRecognizer)->recognize(personNameContext(), ['full_name' => $name]);

    expect(PersonNameRecognizer::clean($name))->toBe($name)
        ->and($result->resolved)->toBe([])
        ->and($result->needsReview)->toBeFalse();
})->with(['Federica Macrì', 'Elisa Fogaça', 'Märtinä Rubicone', 'Liliana Huamán']);

it('AC-003: resolves the cleaned field and flags the row for review', function () {
    $result = (new PersonNameRecognizer)->recognize(personNameContext(), ['full_name' => 'Martina_Lipari']);

    expect($result->resolved)->toBe(['full_name' => 'Martina Lipari'])
        ->and($result->needsReview)->toBeTrue()
        ->and($result->messages)->toBe(['full_name contained special characters and was cleaned to "Martina Lipari"; review it.']);
});

it('AC-003: cleans first_name and last_name mapped directly', function () {
    $result = (new PersonNameRecognizer)->recognize(personNameContext(), ['first_name' => "asja \u{263E}", 'last_name' => 'Rossi']);

    expect($result->resolved)->toBe(['first_name' => 'Asja'])
        ->and($result->needsReview)->toBeTrue();
});

it('AC-003: collapses whitespace without flagging the row', function () {
    $result = (new PersonNameRecognizer)->recognize(personNameContext(), ['full_name' => '  Mario   Rossi ']);

    expect($result->resolved)->toBe(['full_name' => 'Mario Rossi'])
        ->and($result->needsReview)->toBeFalse()
        ->and($result->messages)->toBe([]);
});

it('AC-005: applies the card-form casing without flagging the row', function (string $raw, string $expected) {
    $result = (new PersonNameRecognizer)->recognize(personNameContext(), ['full_name' => $raw]);

    expect($result->resolved)->toBe(['full_name' => $expected])
        ->and($result->needsReview)->toBeFalse();
})->with([
    'upper case' => ['ALESSIA', 'Alessia'],
    'lower case' => ['alessietta dettori', 'Alessietta Dettori'],
    'apostrophe surname' => ["maria d'angelo", "Maria D'Angelo"],
]);

it('AC-005: formats the cleaned name in the review message', function () {
    $result = (new PersonNameRecognizer)->recognize(personNameContext(), ['full_name' => 'Alessietta_dettori']);

    expect($result->resolved)->toBe(['full_name' => 'Alessietta Dettori'])
        ->and($result->messages)->toBe(['full_name contained special characters and was cleaned to "Alessietta Dettori"; review it.']);
});

it('AC-003: is a no-op on a clean or blank name', function (array $mapped) {
    $result = (new PersonNameRecognizer)->recognize(personNameContext(), $mapped);

    expect($result->resolved)->toBe([])
        ->and($result->needsReview)->toBeFalse();
})->with([
    'clean' => [['full_name' => 'Mario Rossi']],
    'blank' => [['full_name' => '   ']],
    'absent' => [['email' => 'mario@example.com']],
]);

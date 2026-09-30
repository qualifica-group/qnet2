<?php

use App\Imports\Support\ColumnMapper;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>
 */
function importMapperFields(): array
{
    return [
        ['id' => 'full_name', 'label' => 'Full name', 'required' => true, 'group' => null, 'type' => 'text'],
        ['id' => 'email', 'label' => 'Email', 'required' => true, 'group' => null, 'type' => 'text'],
        ['id' => 'phone', 'label' => 'Phone', 'required' => false, 'group' => null, 'type' => 'text'],
        ['id' => 'city', 'label' => 'City', 'required' => false, 'group' => null, 'type' => 'text'],
    ];
}

// ---------------------------------------------------------------------------
// AC-003 — ColumnMapper: auto-map (normalization/accents/alias) + all signals
// ---------------------------------------------------------------------------

it('auto-maps columns via normalized name, alias and accent-stripping', function () {
    $fileColumns = [
        ['name' => 'Nome Completo', 'index' => 0, 'duplicate' => false],
        ['name' => 'E-mail', 'index' => 1, 'duplicate' => false],
        ['name' => 'Città', 'index' => 2, 'duplicate' => false],
        ['name' => 'Extra Column', 'index' => 3, 'duplicate' => false],
    ];

    $suggestion = (new ColumnMapper)->suggest($fileColumns, importMapperFields());

    expect($suggestion->mapping)->toBe([
        'Nome Completo' => 'full_name',
        'E-mail' => 'email',
        'Città' => 'city',
    ])
        ->and($suggestion->missingRequired)->toBe([])
        ->and($suggestion->duplicateColumns)->toBe([])
        ->and($suggestion->unusedColumns)->toBe(['Extra Column'])
        ->and($suggestion->conflicts)->toBe([]);
});

it('signals a missing required field, duplicate columns and a mapping conflict', function () {
    $fileColumns = [
        ['name' => 'Nome Completo', 'index' => 0, 'duplicate' => false],
        ['name' => 'Telefono', 'index' => 1, 'duplicate' => true],
        ['name' => 'Telefono', 'index' => 2, 'duplicate' => true],
    ];

    $suggestion = (new ColumnMapper)->suggest($fileColumns, importMapperFields());

    expect($suggestion->mapping)->toBe(['Nome Completo' => 'full_name'])
        ->and($suggestion->missingRequired)->toBe(['email'])
        ->and($suggestion->duplicateColumns)->toBe(['Telefono', 'Telefono#2'])
        ->and($suggestion->conflicts)->toBe(['phone' => ['Telefono', 'Telefono#2']])
        ->and($suggestion->unusedColumns)->toBe([]);
});

it('matches a field on its own id when no alias/label matches', function () {
    $fileColumns = [
        ['name' => 'phone', 'index' => 0, 'duplicate' => false],
    ];

    $suggestion = (new ColumnMapper)->suggest($fileColumns, importMapperFields());

    expect($suggestion->mapping)->toBe(['phone' => 'phone']);
});

it('is a pure function: the same input always yields the same suggestion', function () {
    $fileColumns = [
        ['name' => 'Nome Completo', 'index' => 0, 'duplicate' => false],
        ['name' => 'Email', 'index' => 1, 'duplicate' => false],
    ];

    $mapper = new ColumnMapper;
    $first = $mapper->suggest($fileColumns, importMapperFields());
    $second = $mapper->suggest($fileColumns, importMapperFields());

    expect($first)->toEqual($second);
});

/**
 * @return array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>
 */
function importMapperNameFields(): array
{
    return array_map(
        static fn (string $id): array => ['id' => $id, 'label' => $id, 'required' => false, 'group' => null, 'type' => 'text'],
        ['full_name', 'first_name', 'last_name', 'email'],
    );
}

it('maps a lone "Nome" to full_name and "Indirizzo e-mail" to email', function () {
    $fileColumns = [
        ['name' => 'Nome', 'index' => 0, 'duplicate' => false],
        ['name' => 'Indirizzo e-mail', 'index' => 1, 'duplicate' => false],
    ];

    $suggestion = (new ColumnMapper)->suggest($fileColumns, importMapperNameFields());

    expect($suggestion->mapping)->toBe(['Nome' => 'full_name', 'Indirizzo e-mail' => 'email'])
        ->and($suggestion->conflicts)->toBe([]);
});

it('maps "Nome" to first_name when the file also has a "Cognome" column', function () {
    $fileColumns = [
        ['name' => 'Nome', 'index' => 0, 'duplicate' => false],
        ['name' => 'Cognome', 'index' => 1, 'duplicate' => false],
    ];

    $suggestion = (new ColumnMapper)->suggest($fileColumns, importMapperNameFields());

    expect($suggestion->mapping)->toBe(['Cognome' => 'last_name', 'Nome' => 'first_name'])
        ->and($suggestion->conflicts)->toBe([]);
});

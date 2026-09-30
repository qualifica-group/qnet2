<?php

declare(strict_types=1);

use App\CustomFields\FieldTypeRegistry;
use App\CustomFields\Table\TableFieldSchema;
use App\CustomFields\Types\TableFieldType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function inspectionSchema(array $config = [], bool $required = false): TableFieldSchema
{
    return TableFieldSchema::fromConfig(inspectionTableConfig($config), $required);
}

/**
 * @return array<string, array<int, string>>
 */
function validateTable(TableFieldSchema $schema, mixed $value): array
{
    $rules = collect($schema->rules())->mapWithKeys(fn (array $r, string $k): array => ["f.{$k}" => $r])->all();

    return Validator::make(['f' => $value], $rules)->errors()->toArray();
}

// AC-001
it('AC-001: resolves the table handler with json storage and text filter', function (): void {
    $handler = app(FieldTypeRegistry::class)->resolve('table');

    expect($handler)->toBeInstanceOf(TableFieldType::class)
        ->and($handler->storageType())->toBe('json')
        ->and($handler->columnType())->toBe('table')
        ->and($handler->filterType())->toBe('text');
});

it('normalizes rows: uuid v4 ids, cell types, discards unknown keys and the submitted summary', function (): void {
    $result = inspectionSchema()->normalize(['rows' => [
        ['inspection_date' => '2026-10-12', 'inspector' => ' Rossi ', 'findings' => '3', 'alert_sent' => 1, 'ghost' => 'x'],
        ['id' => 'not-a-uuid', 'inspection_date' => '2026-11-01', 'active' => true],
    ], 'summary' => 'forged']);

    expect($result['rows'])->toHaveCount(2)
        ->and(Str::isUuid($result['rows'][0]['id']))->toBeTrue()
        ->and($result['rows'][0]['id'])->not->toBe($result['rows'][1]['id'])
        ->and($result['rows'][0]['findings'])->toBe(3)
        ->and($result['rows'][0]['alert_sent'])->toBeTrue()
        ->and($result['rows'][0])->not->toHaveKey('ghost')
        ->and($result['rows'][0]['active'])->toBeFalse()
        ->and($result['rows'][1]['active'])->toBeTrue()
        ->and($result['summary'])->toBe('2026-11-01');
});

it('keeps existing uuid ids', function (): void {
    $id = (string) Str::uuid();

    expect(inspectionSchema()->normalize(['rows' => [['id' => $id, 'inspection_date' => '2026-01-01']]])['rows'][0]['id'])->toBe($id);
});

it('returns null for null or malformed input', function (mixed $input): void {
    expect(inspectionSchema()->normalize($input))->toBeNull();
})->with(['null' => [null], 'empty' => [[]], 'string' => ['x'], 'rows not array' => [['rows' => 1]]]);

// AC-009
it('AC-009: computes the summary per strategy', function (string $strategy, ?string $expected, array $rows): void {
    $schema = inspectionSchema(['summary' => ['column' => 'inspection_date', 'strategy' => $strategy]]);

    expect($schema->normalize(['rows' => $rows])['summary'])->toBe($expected);
})->with([
    'selected' => ['selected', '2026-03-01', [
        ['inspection_date' => '2026-01-01'], ['inspection_date' => '2026-03-01', 'active' => true], ['inspection_date' => '2026-09-01'],
    ]],
    'selected but none selected' => ['selected', null, [['inspection_date' => '2026-01-01']]],
    'max' => ['max', '2026-09-01', [['inspection_date' => '2026-01-01'], ['inspection_date' => '2026-09-01'], ['inspection_date' => null]]],
    'min' => ['min', '2026-01-01', [['inspection_date' => '2026-09-01'], ['inspection_date' => '2026-01-01']]],
    'max with zero rows' => ['max', null, []],
    'min with all null' => ['min', null, [['inspection_date' => null]]],
]);

it('compares numeric summary columns numerically', function (): void {
    $schema = inspectionSchema(['summary' => ['column' => 'findings', 'strategy' => 'max']]);

    expect($schema->normalize(['rows' => [['findings' => 9], ['findings' => 10]]])['summary'])->toBe(10);
});

it('has a null summary when none is configured', function (): void {
    expect(inspectionSchema(['summary' => null])->normalize(['rows' => [['inspection_date' => '2026-01-01']]])['summary'])->toBeNull();
});

// AC-013
it('AC-013: resolve keeps only currently defined columns and recomputes the summary', function (): void {
    $stored = ['rows' => [['id' => 'a', 'inspection_date' => '2026-01-01', 'removed_col' => 'x', 'active' => true]], 'summary' => 'stale'];

    $resolved = inspectionSchema()->resolve($stored);

    expect($resolved['rows'][0])->not->toHaveKey('removed_col')
        ->and($resolved['rows'][0]['id'])->toBe('a')
        ->and($resolved['rows'][0]['active'])->toBeTrue()
        ->and($resolved['summary'])->toBe('2026-01-01')
        ->and(inspectionSchema()->resolve(null))->toBeNull();
});

// AC-007 / AC-008 / AC-010
it('AC-007: cell rules are delegated to the scalar handlers', function (array $row, string $errorKey): void {
    expect(validateTable(inspectionSchema(), ['rows' => [$row]]))->toHaveKey($errorKey);
})->with([
    'required missing' => [['inspector' => 'x'], 'f.rows.0.inspection_date'],
    'bad date' => [['inspection_date' => '12/10/2026'], 'f.rows.0.inspection_date'],
    'enum outside options' => [['inspection_date' => '2026-01-01', 'site' => 'moon'], 'f.rows.0.site'],
    'integer above max' => [['inspection_date' => '2026-01-01', 'findings' => 11], 'f.rows.0.findings'],
    'bad id' => [['inspection_date' => '2026-01-01', 'id' => 'nope'], 'f.rows.0.id'],
]);

it('accepts a valid table', function (): void {
    expect(validateTable(inspectionSchema(), ['rows' => [['inspection_date' => '2026-01-01', 'site' => 'on_site', 'findings' => 2]]]))->toBe([]);
});

it('rejects duplicated ids', function (): void {
    $id = (string) Str::uuid();
    $row = ['id' => $id, 'inspection_date' => '2026-01-01'];

    expect(validateTable(inspectionSchema(), ['rows' => [$row, $row]]))->toHaveKey('f.rows.0.id');
});

it('AC-008: rejects more than one selected row on rows', function (): void {
    $rows = [['inspection_date' => '2026-01-01', 'active' => true], ['inspection_date' => '2026-01-02', 'active' => true]];

    expect(validateTable(inspectionSchema(), ['rows' => $rows]))->toHaveKey('f.rows')
        ->and(validateTable(inspectionSchema(), ['rows' => [$rows[0], ['inspection_date' => '2026-01-02', 'active' => false]]]))->toBe([]);
});

it('AC-010: enforces required / min_rows / max_rows on rows', function (bool $required, array $config, int $count, bool $fails): void {
    $rows = array_fill(0, $count, ['inspection_date' => '2026-01-01']);
    $errors = validateTable(inspectionSchema($config, $required), ['rows' => $rows]);

    expect(isset($errors['f.rows']))->toBe($fails);
})->with([
    'required, zero rows' => [true, [], 0, true],
    'required, one row' => [true, [], 1, false],
    'min 2 with one' => [false, ['min_rows' => 2], 1, true],
    'min 2 with two' => [false, ['min_rows' => 2], 2, false],
    'max 3 with four' => [false, ['max_rows' => 3], 4, true],
    'optional zero rows' => [false, [], 0, false],
]);

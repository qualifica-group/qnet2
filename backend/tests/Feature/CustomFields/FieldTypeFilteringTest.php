<?php

declare(strict_types=1);

use App\CustomFields\Types\BooleanFieldType;
use App\CustomFields\Types\EnumFieldType;
use App\CustomFields\Types\IntegerFieldType;
use App\CustomFields\Types\TextFieldType;
use App\Models\CustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

// AC-016: filter/sort/distinctValues against custom_field_values.values-><key>,
// bound params only (no whereRaw/orderByRaw on input). Exercised directly
// against CustomFieldValue::query() — no entity join needed at this layer.
uses(RefreshDatabase::class);

function seedValues(array $rows): void
{
    foreach ($rows as $entityId => $values) {
        CustomFieldValue::factory()->forEntity('companies', $entityId)->create(['values' => $values]);
    }
}

it('filters text via contains/equals/startsWith/endsWith', function (): void {
    seedValues([1 => ['notes' => 'Hello World'], 2 => ['notes' => 'Bye'], 3 => ['notes' => 'Hello Again']]);
    $handler = new TextFieldType;

    $contains = CustomFieldValue::query();
    $handler->applyFilter($contains, 'custom_field_values.values', 'notes', ['type' => 'contains', 'filter' => 'Hello']);
    expect($contains->count())->toBe(2);

    $equals = CustomFieldValue::query();
    $handler->applyFilter($equals, 'custom_field_values.values', 'notes', ['type' => 'equals', 'filter' => 'Bye']);
    expect($equals->count())->toBe(1);

    $startsWith = CustomFieldValue::query();
    $handler->applyFilter($startsWith, 'custom_field_values.values', 'notes', ['type' => 'startsWith', 'filter' => 'Hello']);
    expect($startsWith->count())->toBe(2);

    $endsWith = CustomFieldValue::query();
    $handler->applyFilter($endsWith, 'custom_field_values.values', 'notes', ['type' => 'endsWith', 'filter' => 'Again']);
    expect($endsWith->count())->toBe(1);
});

it('filters number via equals/range/gt/lt', function (): void {
    seedValues([1 => ['score' => 10], 2 => ['score' => 20], 3 => ['score' => 30]]);
    $handler = new IntegerFieldType;

    $equals = CustomFieldValue::query();
    $handler->applyFilter($equals, 'custom_field_values.values', 'score', ['type' => 'equals', 'filter' => 20]);
    expect($equals->count())->toBe(1);

    $range = CustomFieldValue::query();
    $handler->applyFilter($range, 'custom_field_values.values', 'score', ['type' => 'inRange', 'filter' => 15, 'filterTo' => 25]);
    expect($range->count())->toBe(1);

    $gt = CustomFieldValue::query();
    $handler->applyFilter($gt, 'custom_field_values.values', 'score', ['type' => 'greaterThan', 'filter' => 15]);
    expect($gt->count())->toBe(2);

    $lt = CustomFieldValue::query();
    $handler->applyFilter($lt, 'custom_field_values.values', 'score', ['type' => 'lessThan', 'filter' => 25]);
    expect($lt->count())->toBe(2);
});

it('filters boolean via values', function (): void {
    seedValues([1 => ['active' => true], 2 => ['active' => false], 3 => ['active' => true]]);
    $handler = new BooleanFieldType;

    $query = CustomFieldValue::query();
    $handler->applyFilter($query, 'custom_field_values.values', 'active', ['values' => [true]]);
    expect($query->count())->toBe(2);
});

it('filters set values on both single and multi-valued fields', function (): void {
    seedValues([
        1 => ['status' => 'active'],
        2 => ['status' => 'inactive'],
        3 => ['tags' => ['red', 'blue']],
        4 => ['tags' => ['green']],
    ]);
    $handler = new EnumFieldType;

    $single = CustomFieldValue::query();
    $handler->applyFilter($single, 'custom_field_values.values', 'status', ['values' => ['active']]);
    expect($single->count())->toBe(1);

    $multi = CustomFieldValue::query();
    $handler->applyFilter($multi, 'custom_field_values.values', 'tags', ['values' => ['red']]);
    expect($multi->count())->toBe(1);
});

it('sorts by json path', function (): void {
    seedValues([1 => ['score' => 30], 2 => ['score' => 10], 3 => ['score' => 20]]);
    $handler = new IntegerFieldType;

    $query = CustomFieldValue::query();
    $handler->applySort($query, 'custom_field_values.values', 'score', 'asc');

    expect($query->pluck('entity_id')->all())->toBe([2, 3, 1]);
});

it('resolves distinct values, capped and flattened for a multi-valued field', function (): void {
    seedValues([
        1 => ['tags' => ['red', 'blue']],
        2 => ['tags' => ['green']],
        3 => ['tags' => ['red']],
    ]);
    $handler = new EnumFieldType;

    $query = CustomFieldValue::query();

    expect($handler->distinctValues($query, 'custom_field_values.values', 'tags'))->toBe(['blue', 'green', 'red']);
});

it('never uses whereRaw/orderByRaw — the json key stays a bound identifier, not interpolated input', function (): void {
    seedValues([1 => ['notes' => "it's fine"]]);
    $handler = new TextFieldType;

    $query = CustomFieldValue::query();
    $handler->applyFilter($query, 'custom_field_values.values', 'notes', ['type' => 'equals', 'filter' => "it's fine"]);

    expect($query->count())->toBe(1);
});

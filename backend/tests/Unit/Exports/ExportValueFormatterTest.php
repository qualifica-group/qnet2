<?php

use App\Exports\ExportValueFormatter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

function exportValueFormatter(): ExportValueFormatter
{
    return new ExportValueFormatter;
}

it('formats a datetime value with the configured format', function () {
    config(['exports.datetime_format' => 'Y-m-d H:i:s']);

    expect(exportValueFormatter()->format(Carbon::create(2026, 7, 3, 10, 30, 0), 'datetime'))
        ->toBe('2026-07-03 10:30:00');
});

it('formats null datetime as an empty string', function () {
    expect(exportValueFormatter()->format(null, 'datetime'))->toBe('');
});

it('localizes boolean true/false', function () {
    app()->setLocale('en');

    expect(exportValueFormatter()->format(true, 'boolean'))->toBe('Yes')
        ->and(exportValueFormatter()->format(false, 'boolean'))->toBe('No');
});

it('formats null boolean as an empty string', function () {
    expect(exportValueFormatter()->format(null, 'boolean'))->toBe('');
});

it('joins a tags array with "; "', function () {
    expect(exportValueFormatter()->format(['Sales', 'Support'], 'tags'))->toBe('Sales; Support');
});

it('joins array items that are related-entity summaries by their name', function () {
    $value = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];

    expect(exportValueFormatter()->format($value, 'tags'))->toBe('Alice; Bob');
});

it('formats an empty array as an empty string', function () {
    expect(exportValueFormatter()->format([], 'tags'))->toBe('');
});

it('casts null to an empty string for any other type', function () {
    expect(exportValueFormatter()->format(null, 'text'))->toBe('')
        ->and(exportValueFormatter()->format(null, 'number'))->toBe('');
});

it('casts a scalar value to its string form for any other type', function () {
    expect(exportValueFormatter()->format('Acme', 'text'))->toBe('Acme')
        ->and(exportValueFormatter()->format(42, 'number'))->toBe('42');
});

it('formats a single related-entity summary by its name for any other type', function () {
    expect(exportValueFormatter()->format(['id' => 7, 'name' => 'Mario Rossi', 'avatar_url' => null], 'text'))->toBe('Mario Rossi');
});

<?php

declare(strict_types=1);

use App\CustomFields\Table\TableRowTrigger;

// Spec 0390 AC-003: which rows of a table value fire the field's row event.

const TRIGGER_ROW_A = '11111111-1111-4111-8111-111111111111';
const TRIGGER_ROW_B = '22222222-2222-4222-8222-222222222222';

/**
 * @param  array<int, array<string, mixed>>  $rows
 * @return array{rows: array<int, array<string, mixed>>}
 */
function triggerTableValue(array $rows): array
{
    return ['rows' => $rows];
}

dataset('trigger column cases', [
    'new row switched on fires' => [null, [['id' => TRIGGER_ROW_A, 'flag' => true]], [TRIGGER_ROW_A]],
    'new row left off does not fire' => [null, [['id' => TRIGGER_ROW_A, 'flag' => false]], []],
    'existing row switched on fires' => [[['id' => TRIGGER_ROW_A, 'flag' => false]], [['id' => TRIGGER_ROW_A, 'flag' => true]], [TRIGGER_ROW_A]],
    'row already on does not fire again' => [[['id' => TRIGGER_ROW_A, 'flag' => true]], [['id' => TRIGGER_ROW_A, 'flag' => true]], []],
    'row stored as 1 is already on' => [[['id' => TRIGGER_ROW_A, 'flag' => 1]], [['id' => TRIGGER_ROW_A, 'flag' => true]], []],
    'row switched off does not fire' => [[['id' => TRIGGER_ROW_A, 'flag' => true]], [['id' => TRIGGER_ROW_A, 'flag' => false]], []],
    'only the switched row fires' => [
        [['id' => TRIGGER_ROW_A, 'flag' => true]],
        [['id' => TRIGGER_ROW_A, 'flag' => true], ['id' => TRIGGER_ROW_B, 'flag' => true]],
        [TRIGGER_ROW_B],
    ],
]);

it('fires on a trigger column only when a row turns it on', function (?array $before, array $after, array $expected): void {
    $config = ['notify' => ['trigger' => 'flag']];

    $fired = TableRowTrigger::firedRows($config, $before === null ? null : triggerTableValue($before), triggerTableValue($after));

    expect(array_column($fired, 'id'))->toBe($expected);
})->with('trigger column cases');

it('fires for every added row when there is no trigger column', function (): void {
    $config = ['notify' => ['trigger' => null]];

    $fired = TableRowTrigger::firedRows($config, triggerTableValue([['id' => TRIGGER_ROW_A]]), triggerTableValue([['id' => TRIGGER_ROW_A], ['id' => TRIGGER_ROW_B]]));

    expect(array_column($fired, 'id'))->toBe([TRIGGER_ROW_B]);
});

it('fires nothing when the field has no notification or no rows', function (): void {
    expect(TableRowTrigger::firedRows([], null, triggerTableValue([['id' => TRIGGER_ROW_A, 'flag' => true]])))->toBe([])
        ->and(TableRowTrigger::firedRows(['notify' => ['trigger' => null]], triggerTableValue([['id' => TRIGGER_ROW_A]]), null))->toBe([]);
});

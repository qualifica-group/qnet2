<?php

use App\WorkOrders\WorkOrderAttributeLock;
use App\WorkOrders\WorkOrderAttributeLocks;

it('locks nothing without a registered lock', function () {
    expect(app(WorkOrderAttributeLocks::class)->lockedCodes([1, 2]))->toBe([]);
});

it('unions the codes of the tagged locks and asks nothing for an empty category list', function () {
    $lock = new class implements WorkOrderAttributeLock
    {
        public int $calls = 0;

        public function lockedCodes(array $categoryIds): array
        {
            $this->calls++;

            return $categoryIds === [7] ? ['a', 'b', 'a'] : [];
        }
    };
    app()->instance('test.lock', $lock);
    app()->tag(['test.lock'], 'work-orders.attribute-locks');
    $locks = app(WorkOrderAttributeLocks::class);

    expect($locks->lockedCodes([7]))->toBe(['a', 'b'])
        ->and($locks->lockedCodes([]))->toBe([])
        ->and($lock->calls)->toBe(1);
});

it('reports only the locked codes a submission really changes', function () {
    $locks = app(WorkOrderAttributeLocks::class);
    $current = ['hours' => 120, 'state' => 'Open', 'empty' => null];

    expect($locks->changedCodes(['hours', 'state', 'empty', 'absent'], $current, [
        'hours' => '120', 'state' => ' Open ', 'empty' => '', 'free' => 'x',
    ]))->toBe([])
        ->and($locks->changedCodes(['hours', 'state', 'absent'], $current, [
            'hours' => 121, 'state' => 'Open', 'absent' => 'new',
        ]))->toBe(['hours', 'absent']);
});

<?php

use App\Services\Invoices\InstallmentRebalancer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// The ValidationException needs the container (translator/validator); no database involved.
uses(TestCase::class);

/**
 * @param  array<int, array{0: string, 1: string|null}>  $amounts  [amount, collected] per installment, due dates 30 days apart
 * @return array<int, array<string, mixed>>
 */
function rebalancerRows(array $amounts): array
{
    $rows = [];

    foreach ($amounts as $index => [$amount, $collected]) {
        $rows[] = [
            'id' => $index + 10,
            'sequence' => $index + 1,
            'due_date' => CarbonImmutable::parse('2026-04-01')->addDays(30 * $index),
            'amount' => $amount,
            'payment_method_code' => 'MP05',
            'collected_amount' => $collected,
        ];
    }

    return $rows;
}

it('splits an amount in equal parts with the remainder on the last one', function () {
    $rebalancer = new InstallmentRebalancer;

    expect($rebalancer->equalParts('600.01', 2))->toBe(['300.00', '300.01'])
        ->and($rebalancer->equalParts('100.00', 3))->toBe(['33.33', '33.33', '33.34'])
        ->and($rebalancer->equalParts('0.02', 3))->toBe(['0.00', '0.00', '0.02'])
        ->and($rebalancer->equalParts('50.00', 1))->toBe(['50.00']);
});

it('spreads the residual on the open installments and keeps their due dates', function () {
    $plan = (new InstallmentRebalancer)->rebalance(rebalancerRows([['400.00', '400.00'], ['400.00', null], ['400.00', null]]), '1000.00');

    expect(array_column($plan['rows'], 'amount'))->toBe(['400.00', '300.00', '300.00'])
        ->and(array_column($plan['rows'], 'locked'))->toBe([true, false, false])
        ->and(array_column($plan['rows'], 'id'))->toBe([10, 11, 12])
        ->and($plan['rows'][2]['due_date']->toDateString())->toBe('2026-05-31')
        ->and($plan['deleted_ids'])->toBe([]);
});

it('closes a partially collected installment at what was collected', function () {
    $plan = (new InstallmentRebalancer)->rebalance(rebalancerRows([['400.00', '200.00'], ['400.00', null], ['400.00', null]]), '1200.00');

    expect(array_column($plan['rows'], 'amount'))->toBe(['200.00', '500.00', '500.00'])
        ->and($plan['rows'][0]['collected_amount'])->toBe('200.00');
});

it('drops the open installments when the total equals the collected amount', function () {
    $plan = (new InstallmentRebalancer)->rebalance(rebalancerRows([['400.00', '400.00'], ['400.00', null], ['400.00', null]]), '400.00');

    expect(array_column($plan['rows'], 'sequence'))->toBe([1])->and($plan['deleted_ids'])->toBe([11, 12]);
});

it('rejects a total lower than the collected amount on the requested field', function (string $field) {
    $rows = rebalancerRows([['400.00', '400.00'], ['400.00', null]]);

    try {
        (new InstallmentRebalancer)->rebalance($rows, '399.99', $field);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([$field => ['The document total cannot be lower than the amount already collected.']]);

        return;
    }

    $this->fail('ValidationException expected');
})->with(['lines', 'total_amount']);

it('appends an installment 30 days after the last one when none is open', function () {
    $plan = (new InstallmentRebalancer)->rebalance(rebalancerRows([['400.00', '400.00'], ['400.00', '400.00']]), '900.00');
    $added = $plan['rows'][2];

    expect($added)->toMatchArray(['id' => null, 'sequence' => 3, 'amount' => '100.00', 'payment_method_code' => 'MP05', 'locked' => false])
        ->and($added['due_date']->toDateString())->toBe('2026-05-31')
        ->and(InstallmentRebalancer::RESIDUAL_INSTALLMENT_DAYS)->toBe(30);
});

it('appends the new installment after the highest sequence when open rows were removed earlier', function () {
    $rows = rebalancerRows([['400.00', '400.00'], ['400.00', '400.00']]);
    $rows[0]['sequence'] = 2;
    $rows[1]['sequence'] = 5;

    expect((new InstallmentRebalancer)->rebalance($rows, '850.50')['rows'][2]['sequence'])->toBe(6);
});

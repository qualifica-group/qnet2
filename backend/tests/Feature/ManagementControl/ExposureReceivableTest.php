<?php

use App\Enums\InvoiceType;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0269, AC-003 .. AC-007 / AC-009 — the receivable side.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-003: every open residual lands in its bucket and the closed installment does not count', function () {
    mcReceivable([
        ['due' => -100, 'amount' => '100.00'],
        ['due' => -45, 'amount' => '200.00'],
        ['due' => -10, 'amount' => '300.00'],
        ['due' => 5, 'amount' => '400.00'],
        ['due' => 45, 'amount' => '500.00'],
        ['due' => 90, 'amount' => '600.00'],
        ['due' => -20, 'amount' => '999.00', 'collected' => '999.00'],
    ]);

    $side = mcGet('exposure')->assertOk()->json('data.receivable');

    expect($side['aging'])->toEqual([
        'due_0_30' => 400, 'due_31_60' => 500, 'due_over_60' => 600,
        'overdue_1_30' => 300, 'overdue_31_60' => 200, 'overdue_61_90' => 0, 'overdue_over_90' => 100,
    ])->and($side['overdue_total'])->toEqual(600)
        ->and($side['not_due_total'])->toEqual(1500)
        ->and($side['open_total'])->toEqual(2100);
});

it('AC-003: the bucket limits are inclusive on the days late', function () {
    $offsets = ['due_0_30' => [0, 30], 'due_31_60' => [31, 60], 'due_over_60' => [61, 400], 'overdue_1_30' => [-1, -30], 'overdue_31_60' => [-31, -60], 'overdue_61_90' => [-61, -90], 'overdue_over_90' => [-91, -400]];
    $installments = [];
    foreach ($offsets as $limits) {
        foreach ($limits as $due) {
            $installments[] = ['due' => $due, 'amount' => '1.00'];
        }
    }
    mcReceivable($installments);

    $aging = mcGet('exposure')->assertOk()->json('data.receivable.aging');

    expect($aging)->toEqual(array_fill_keys(array_keys($offsets), 2));
});

it('AC-004: the period bounds the flows, never the open schedule (D-1)', function () {
    mcReceivable([['due' => -20, 'amount' => '100.00', 'collected' => '60.00', 'collected_at' => -5], ['due' => 20, 'amount' => '40.00']], ['document_date' => mcDay(-10)]);
    mcReceivable([['due' => -300, 'amount' => '500.00', 'collected' => '500.00', 'collected_at' => -300], ['due' => -10, 'amount' => '70.00']], ['document_date' => mcDay(-400)]);

    $all = mcGet('exposure')->assertOk()->json('data.receivable');
    $period = mcGet('exposure', ['date_from' => mcDay(-30), 'date_to' => mcDay(0)])->assertOk()->json('data.receivable');
    $empty = mcGet('exposure', ['date_from' => mcDay(10), 'date_to' => mcDay(20)])->assertOk()->json('data.receivable');

    expect($period['invoiced_in_period'])->toEqual(140)
        ->and($period['settled_in_period'])->toEqual(60)
        ->and($all['invoiced_in_period'])->toEqual(710)
        ->and($all['settled_in_period'])->toEqual(560)
        ->and($empty['invoiced_in_period'])->toEqual(0)
        ->and($empty['settled_in_period'])->toEqual(0);

    foreach ([$all, $period, $empty] as $side) {
        expect($side['open_total'])->toEqual(150)->and($side['aging'])->toEqual($all['aging']);
    }
});

it('AC-005: the average days are weighted on the collected amount, null without collections', function () {
    mcReceivable([['due' => 0, 'amount' => '100.00', 'collected' => '100.00', 'collected_at' => -30]], ['document_date' => mcDay(-40)]);
    mcReceivable([['due' => 0, 'amount' => '300.00', 'collected' => '300.00', 'collected_at' => -30]], ['document_date' => mcDay(-60)]);
    $period = ['date_from' => mcDay(-31), 'date_to' => mcDay(0)];

    expect(mcGet('exposure', $period)->assertOk()->json('data.receivable.average_days'))->toBe(25)
        ->and(mcGet('exposure', ['date_from' => mcDay(1)])->assertOk()->json('data.receivable.average_days'))->toBeNull();
});

it('AC-006: the status comes from the oldest open installment of each document', function () {
    mcReceivable([['due' => 5, 'amount' => '10.00']]);
    mcReceivable([['due' => -10, 'amount' => '20.00']]);
    mcReceivable([['due' => -40, 'amount' => '5.00', 'collected' => '5.00'], ['due' => -10, 'amount' => '30.00']]);
    mcReceivable([['due' => -30, 'amount' => '40.00'], ['due' => 10, 'amount' => '1.00']]);
    mcReceivable([['due' => -21, 'amount' => '7.00']]);
    mcReceivable([['due' => -22, 'amount' => '9.00']]);
    mcReceivable([['due' => -20, 'amount' => '50.00', 'collected' => '50.00']]);

    $status = mcGet('exposure')->assertOk()->json('data.receivable.by_status');

    expect($status)->toEqual([
        'paid' => ['documents' => 1, 'open' => 0],
        'not_due' => ['documents' => 1, 'open' => 10],
        'overdue' => ['documents' => 3, 'open' => 57],
        'seriously_overdue' => ['documents' => 2, 'open' => 50],
    ]);
});

it('AC-007: the top has the highest overdue first, with the worst bucket of each registry', function () {
    $names = collect(['Alfa', 'Beta', 'Gamma', 'Delta'])->map(fn (string $name): Registry => Registry::factory()->create(['name' => $name]));
    [$alfa, $beta, $gamma, $paid] = $names->all();
    mcReceivable([['due' => -20, 'amount' => '500.00'], ['due' => 50, 'amount' => '80.00']], ['customer_registry_id' => $alfa->id]);
    mcReceivable([['due' => -70, 'amount' => '300.00']], ['customer_registry_id' => $beta->id]);
    mcReceivable([['due' => 10, 'amount' => '100.00']], ['customer_registry_id' => $gamma->id]);
    mcReceivable([['due' => -10, 'amount' => '60.00', 'collected' => '60.00']], ['customer_registry_id' => $paid->id]);

    $few = mcGet('exposure', ['top' => 2])->assertOk()->json('data.receivable.top');
    $all = mcGet('exposure', ['top' => 10])->assertOk()->json('data.receivable.top');

    expect(array_column(array_column($few, 'registry'), 'name'))->toBe(['Alfa', 'Beta'])
        ->and($few[0])->toMatchArray(['open' => 580, 'overdue' => 500, 'worst_bucket' => 'overdue_1_30'])
        ->and($few[1])->toMatchArray(['open' => 300, 'overdue' => 300, 'worst_bucket' => 'overdue_61_90'])
        ->and(array_column(array_column($all, 'registry'), 'name'))->toBe(['Alfa', 'Beta', 'Gamma'])
        ->and($all[2])->toMatchArray(['open' => 100, 'overdue' => 0, 'worst_bucket' => 'due_0_30'])
        ->and($all[0]['registry']['id'])->toBe($alfa->id);
});

it('AC-009: the document type chooses proforma and invoices on the receivable side, invoice by default', function () {
    mcReceivable([['due' => 10, 'amount' => '100.00']]);
    mcReceivable([['due' => 10, 'amount' => '30.00']], ['type' => InvoiceType::Proforma]);

    $open = fn (array $params): float => mcGet('exposure', $params)->assertOk()->json('data.receivable.open_total');

    expect($open([]))->toEqual(100)
        ->and($open(['document_type' => 'invoice']))->toEqual(100)
        ->and($open(['document_type' => 'proforma']))->toEqual(30)
        ->and($open(['document_type' => 'all']))->toEqual(130);
});

<?php

use App\Enums\CostTypeNature;
use App\Models\CostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0270, AC-008 / AC-009 — the previous year comparison, the empty months and the costs by nature.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-008: the previous year reports the same measures twelve months back, with zeros where there is nothing', function () {
    mcTrendInvoice('2026-02-10', [['100.00', null]]);
    mcTrendInvoice('2025-02-20', [['70.00', null]]);
    mcTrendInvoice('2025-04-05', [['15.00', null]]);
    mcTrendCost('2026-02-10', '40.00');
    mcTrendCost('2025-03-10', '25.00');
    mcTrendCost('2025-01-31', '9.00');

    $data = mcGet('monthly-trend', ['date_from' => '2026-01-15', 'date_to' => '2026-04-20'])->assertOk()->json('data');

    expect($data['months'])->toBe(['2026-01', '2026-02', '2026-03', '2026-04'])
        ->and($data['totals']['revenue'])->toEqual([0, 100, 0, 0])
        ->and($data['totals']['cost'])->toEqual([0, 40, 0, 0])
        ->and($data['totals']['previous_year'])->toEqual([
            'revenue' => [0, 70, 0, 15],
            'cost' => [9, 0, 25, 0],
            'margin' => [-9, 70, -25, 15],
        ]);
});

it('AC-008: the months are those of the interval, whatever the day', function () {
    mcTrendInvoice('2026-03-01', [['10.00', null]]);
    mcTrendInvoice('2026-03-31', [['5.00', null]]);
    mcTrendInvoice('2026-04-01', [['99.00', null]]);

    $data = mcGet('monthly-trend', ['date_from' => '2026-03-20', 'date_to' => '2026-03-21'])->assertOk()->json('data');

    expect($data['months'])->toBe(['2026-03'])
        ->and($data['totals']['revenue'])->toEqual([15]);
});

it('AC-009: the costs by nature add up to the cost totals and the share is on the revenue of the month', function () {
    $direct = CostType::factory()->create(['nature' => CostTypeNature::Direct]);
    $indirect = CostType::factory()->create(['nature' => CostTypeNature::Indirect]);
    $general = CostType::factory()->create(['nature' => CostTypeNature::General]);

    mcTrendInvoice('2026-01-10', [['1000.00', null]]);
    foreach ([[$direct, '100.00'], [$indirect, '50.00'], [$general, '33.33']] as [$type, $net]) {
        mcTrendCost('2026-01-12', $net, line: ['cost_type_id' => $type->id]);
    }
    mcTrendCost('2026-02-12', '20.00', line: ['cost_type_id' => $direct->id]);

    $data = mcGet('monthly-trend', ['date_from' => '2026-01-01', 'date_to' => '2026-02-28'])->assertOk()->json('data');
    $rows = collect($data['by_nature'])->keyBy('nature');

    expect(array_column($data['by_nature'], 'nature'))->toBe(['direct', 'indirect', 'general'])
        ->and($rows['direct']['cost'])->toEqual([100, 20])
        ->and($rows['indirect']['cost'])->toEqual([50, 0])
        ->and($rows['general']['cost'])->toEqual([33.33, 0])
        ->and($rows['direct']['share_of_revenue'])->toEqual([10, null])
        ->and($rows['indirect']['share_of_revenue'])->toEqual([5, null])
        ->and($rows['general']['share_of_revenue'])->toEqual([3.3, null]);

    foreach ($data['months'] as $index => $month) {
        expect(round(array_sum(array_map(fn (array $row): float => $row['cost'][$index], $data['by_nature'])), 2))->toEqual($data['totals']['cost'][$index]);
    }
});

<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0272, AC-005 / AC-008 — the internal labor cost in the monthly trend.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

const LABOR_TREND_PERIOD = ['date_from' => '2026-01-01', 'date_to' => '2026-03-31'];

it('AC-005: the hours of a commessa follow its functions, the others go to structure, the cost includes the labor', function () {
    [$alpha, $beta] = [BusinessFunction::factory()->create(['name' => 'Alpha function']), BusinessFunction::factory()->create(['name' => 'Beta function'])];
    $workOrder = mcWorkOrder();
    mcRevenueLine($workOrder, '300.00', $alpha);
    mcRevenueLine($workOrder, '100.00', $beta);

    mcTrendInvoice('2026-02-10', [['500.00', null]]);
    mcTrendCost('2026-02-12', '40.00', $workOrder);
    mcTimeEntry($workOrder, 120, '50.00', '2026-02-05');
    mcTimeEntry($workOrder, 60, null, '2026-02-06');
    mcTimeEntry(null, 60, '30.00', '2026-02-07');
    mcTimeEntry($workOrder, 600, '50.00', '2026-04-05');

    $data = mcGet('monthly-trend', LABOR_TREND_PERIOD)->assertOk()->json('data');
    $rows = collect($data['functions'])->keyBy('key');

    expect($rows[(string) $alpha->id]['labor'])->toEqual([0, 75, 0])
        ->and($rows[(string) $alpha->id]['cost'])->toEqual([0, 105, 0])
        ->and($rows[(string) $beta->id]['labor'])->toEqual([0, 25, 0])
        ->and($rows[(string) $beta->id]['cost'])->toEqual([0, 35, 0])
        ->and($rows['structure']['labor'])->toEqual([0, 30, 0])
        ->and($rows['structure']['cost'])->toEqual([0, 30, 0])
        ->and($rows['structure']['margin'])->toEqual([0, -30, 0])
        ->and($data['totals']['labor'])->toEqual([0, 130, 0])
        ->and($data['totals']['cost'])->toEqual([0, 170, 0])
        ->and($data['totals']['margin'])->toEqual([0, 330, 0])
        ->and($data['totals']['labor_minutes'])->toBe([0, 240, 0])
        ->and($data['totals']['unpriced_minutes'])->toBe([0, 60, 0])
        ->and(round($rows->sum(fn (array $row): float => array_sum($row['labor'])), 2))->toEqual(130);
});

it('AC-005: the previous year reads the labor of the months twelve months back and the nature stays on payable costs', function () {
    mcTimeEntry(null, 60, '40.00', '2026-02-07');
    mcTimeEntry(null, 90, '40.00', '2025-02-07');
    mcTimeEntry(null, 30, '40.00', '2025-03-07');

    $data = mcGet('monthly-trend', LABOR_TREND_PERIOD)->assertOk()->json('data');

    expect($data['totals']['previous_year']['labor'])->toEqual([0, 60, 20])
        ->and($data['totals']['previous_year']['cost'])->toEqual([0, 60, 20])
        ->and($data['totals']['previous_year']['margin'])->toEqual([0, -60, -20])
        ->and($data['totals']['labor_minutes'])->toBe([0, 60, 0])
        ->and(array_sum(array_merge(...array_column($data['by_nature'], 'cost'))))->toEqual(0);
});

it('AC-008: the number of queries does not depend on the commesse and time entries', function () {
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $workOrder = mcWorkOrder();
            mcRevenueLine($workOrder, '100.00', BusinessFunction::factory()->create());
            mcTimeEntry($workOrder, 60, '20.00', '2026-02-05');
            mcTimeEntry(null, 30, null, '2025-02-05');
        }
    };

    $seed(5);
    $few = mcQueryCount('monthly-trend', LABOR_TREND_PERIOD);
    $seed(20);

    expect(mcQueryCount('monthly-trend', LABOR_TREND_PERIOD))->toBe($few);
});

it('AC-005: the company filter keeps the hours of the users of that company, the site filters do not touch them', function () {
    $alfa = Company::factory()->create();
    $beta = Company::factory()->create();
    $inCompany = fn (Company $company): User => User::factory()->withEmployment(fn ($f) => $f->state(['company_id' => $company->id, 'hourly_cost' => '60.00']))->create();

    mcTimeEntry(null, 60, null, '2026-02-05', $inCompany($alfa));
    mcTimeEntry(null, 120, null, '2026-02-06', $inCompany($beta));
    mcTimeEntry(null, 30, '60.00', '2026-02-07');
    mcTimeEntry(null, 30, null, '2026-02-08', User::factory()->create());

    $filtered = mcGet('monthly-trend', [...LABOR_TREND_PERIOD, 'company_id' => [$alfa->id]])->assertOk()->json('data.totals');
    $sites = mcGet('monthly-trend', [...LABOR_TREND_PERIOD, 'operational_site_id' => [OperationalSite::factory()->create()->id]])->assertOk()->json('data.totals');
    $all = mcGet('monthly-trend', LABOR_TREND_PERIOD)->assertOk()->json('data.totals');

    expect($filtered['labor'])->toEqual([0, 60, 0])
        ->and($filtered['labor_minutes'])->toBe([0, 60, 0])
        ->and($sites['labor_minutes'])->toBe($all['labor_minutes'])
        ->and($all['labor_minutes'])->toBe([0, 240, 0]);
});

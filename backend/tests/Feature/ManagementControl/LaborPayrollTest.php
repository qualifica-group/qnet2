<?php

use App\Models\BusinessFunction;
use App\Models\PayrollCost;
use App\Models\User;
use App\Services\ManagementControl\LaborCostSource;
use App\Services\ManagementControl\Payroll\PayrollMonthMinutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Spec 0430, AC-007 / AC-008 / AC-009 — the real labor cost from the imported payroll.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

const PAYROLL_TREND_PERIOD = ['date_from' => '2026-09-01', 'date_to' => '2026-10-31'];

$payroll = fn (User $user, string $total, string $period = '2026-10') => PayrollCost::factory()->create(['user_id' => $user->id, 'period' => $period, 'total_cost' => $total]);

it('AC-007: the month cost of two companies is spread over the hours, September keeps the contract rate', function () use ($payroll) {
    $user = User::factory()->withEmployment(fn ($factory) => $factory->paidHourly('30.00'))->create();
    [$a, $b] = [mcWorkOrder(), mcWorkOrder()];
    $payroll($user, '1600.00');
    $payroll($user, '1000.00');

    mcTimeEntry($a, 6000, null, '2026-10-05', $user);
    mcTimeEntry($b, 2400, null, '2026-10-06', $user);
    mcTimeEntry(null, 1200, null, '2026-10-07', $user);
    mcTimeEntry($a, 60, null, '2026-09-05', $user);

    $source = app(LaborCostSource::class);
    $byWorkOrder = $source->byWorkOrder([$a->id, $b->id]);

    expect($byWorkOrder[$a->id]['cents'])->toBe(162500 + 3000)
        ->and($byWorkOrder[$a->id]['minutes'])->toBe(6060)
        ->and($byWorkOrder[$a->id]['unpriced_minutes'])->toBe(0)
        ->and($byWorkOrder[$b->id]['cents'])->toBe(65000)
        ->and($byWorkOrder[$b->id]['unpriced_minutes'])->toBe(0);

    $october = collect($source->byMonth('2026-10-01', '2026-11-01'))->firstWhere(fn (array $row) => $row['work_order_id'] === null);
    expect($october)->toMatchArray(['month' => '2026-10', 'user_id' => $user->id, 'cents' => 32500, 'minutes' => 1200, 'unpriced_minutes' => 0]);

    $trend = mcGet('monthly-trend', PAYROLL_TREND_PERIOD)->assertOk()->json('data.totals');
    expect($trend['labor'])->toEqual([30, 2600])
        ->and($trend['unpriced_minutes'])->toBe([0, 0]);
});

it('AC-008: a cost without hours reaches no work order and the functions of the user in the monthly trend', function () use ($payroll) {
    $function = BusinessFunction::factory()->create(['name' => 'Delivery']);
    $member = User::factory()->create();
    $function->users()->attach($member->id);
    $loner = User::factory()->create();
    $workOrder = mcWorkOrder();
    $payroll($member, '500.00');
    $payroll($loner, '200.00');

    expect(app(LaborCostSource::class)->byWorkOrder([$workOrder->id]))->toBe([]);

    $rows = collect(app(LaborCostSource::class)->byMonth('2026-10-01', '2026-11-01'))->keyBy('user_id');
    expect($rows[$member->id])->toMatchArray(['work_order_id' => null, 'minutes' => 0, 'unpriced_minutes' => 0, 'cents' => 50000]);

    $data = mcGet('monthly-trend', PAYROLL_TREND_PERIOD)->assertOk()->json('data');
    $functions = collect($data['functions'])->keyBy('key');

    expect($functions[(string) $function->id]['labor'])->toEqual([0, 500])
        ->and($functions['structure']['labor'])->toEqual([0, 200])
        ->and($data['totals']['labor_minutes'])->toBe([0, 0]);
});

it('AC-009: the shares of a month not evenly divisible differ from the cost by at most one cent per group', function () use ($payroll) {
    $user = User::factory()->create();
    [$a, $b] = [mcWorkOrder(), mcWorkOrder()];
    $payroll($user, '1000.00');
    mcTimeEntry($a, 3, null, '2026-10-05', $user);
    mcTimeEntry($b, 2, null, '2026-10-06', $user);
    mcTimeEntry(null, 2, null, '2026-10-07', $user);

    $source = app(LaborCostSource::class);
    $byWorkOrder = $source->byWorkOrder([$a->id, $b->id]);
    $noOrder = collect($source->byMonth('2026-10-01', '2026-11-01'))->firstWhere(fn (array $row) => $row['work_order_id'] === null);
    $sum = $byWorkOrder[$a->id]['cents'] + $byWorkOrder[$b->id]['cents'] + $noOrder['cents'];

    expect($byWorkOrder[$a->id]['cents'])->toBe(42857)
        ->and(abs($sum - 100000))->toBeLessThanOrEqual(3);
});

it('AC-007: the number of queries does not depend on the users with payroll', function () use ($payroll) {
    $seed = function (int $count) use ($payroll): void {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            $payroll($user, '100.00');
            mcTimeEntry(mcWorkOrder(), 60, null, '2026-10-05', $user);
        }
    };
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(LaborCostSource::class)->byMonth('2026-10-01', '2026-11-01');

        return count(DB::getQueryLog());
    };

    $seed(2);
    $few = $count();
    $seed(10);

    expect($count())->toBe($few);
});

it('AC-010: the month minutes of the payroll pairs split total and work order minutes', function () {
    [$user, $other] = [User::factory()->create(), User::factory()->create()];
    $workOrder = mcWorkOrder();
    mcTimeEntry($workOrder, 100, null, '2026-10-05', $user);
    mcTimeEntry(null, 20, null, '2026-10-06', $user);
    mcTimeEntry($workOrder, 50, null, '2026-09-06', $user);
    mcTimeEntry($workOrder, 10, null, '2026-10-06', $other);

    $minutes = (new PayrollMonthMinutes)->forPairs([[$user->id, '2026-10'], [$user->id, '2026-08']]);

    expect($minutes)->toBe([
        PayrollMonthMinutes::key($user->id, '2026-10') => ['total_minutes' => 120, 'work_order_minutes' => 100],
    ])->and((new PayrollMonthMinutes)->forPairs([]))->toBe([]);
});

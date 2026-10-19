<?php

use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\ManagementControl\McCatalogue;
use Database\Seeders\ManagementControl\McDealPlanner;
use Database\Seeders\ManagementControl\McTimeline;
use Database\Seeders\ManagementControl\McTimesheet;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// The building blocks of DemoManagementControlSeeder with logic of their own; the
// whole dataset runs through the real services and is verified on MySQL by hand.
uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

it('runs the events in date order with the clock on their day, queued ones included, and drops the future ones', function () {
    $timeline = new McTimeline;
    $ran = [];
    $record = function (string $label) use (&$ran): Closure {
        return function () use ($label, &$ran): void {
            $ran[] = [$label, now()->toDateString()];
        };
    };

    $timeline->at('2025-03-10', function () use ($timeline, $record, &$ran): void {
        $ran[] = ['2025-03-10', now()->toDateString()];
        $timeline->at('2025-03-20', $record('2025-03-20'));
    });
    $timeline->at('2025-01-02', $record('2025-01-02'));
    $timeline->at('2025-03-10', $record('second same day'));
    $timeline->at(CarbonImmutable::today()->addDay()->toDateString(), $record('future'));

    expect($timeline->run())->toBe(4)
        ->and($ran)->toBe([
            ['2025-01-02', '2025-01-02'],
            ['2025-03-10', '2025-03-10'],
            ['second same day', '2025-03-10'],
            ['2025-03-20', '2025-03-20'],
        ])
        ->and(now()->toDateString())->toBe(CarbonImmutable::today()->toDateString());
});

it('skips weekends, bank holidays, Easter Monday and the company closures', function () {
    expect(McTimeline::isWorkingDay(CarbonImmutable::parse('2025-04-21')))->toBeFalse()
        ->and(McTimeline::isWorkingDay(CarbonImmutable::parse('2025-06-02')))->toBeFalse()
        ->and(McTimeline::isWorkingDay(CarbonImmutable::parse('2025-08-13')))->toBeFalse()
        ->and(McTimeline::isWorkingDay(CarbonImmutable::parse('2025-03-08')))->toBeFalse()
        ->and(McTimeline::isWorkingDay(CarbonImmutable::parse('2025-03-10')))->toBeTrue()
        ->and(McTimeline::nextWorkingDay(CarbonImmutable::parse('2025-12-24'))->toDateString())->toBe('2026-01-07');
});

it('plans deals over the whole period without two open deals on the same customer', function () {
    $faker = FakerFactory::create('it_IT');
    $faker->seed(McCatalogue::FAKER_SEED);

    $deals = (new McDealPlanner)->plan($faker, range(1, 40));
    $byCustomer = collect($deals)->groupBy('customer_id');

    expect(count($deals))->toBeGreaterThan(200)
        ->and(collect($deals)->min('opened_on'))->toStartWith('2025-01')
        ->and(collect($deals)->max('opened_on'))->toStartWith('2026-10');

    foreach ($byCustomer as $customerDeals) {
        $sorted = $customerDeals->sortBy('opened_on')->values();
        for ($i = 1; $i < $sorted->count(); $i++) {
            expect($sorted[$i]['opened_on'] > $sorted[$i - 1]['closes_on'])->toBeTrue();
        }
    }

    foreach ($deals as $deal) {
        expect($deal['lines'])->not->toBeEmpty()
            ->and($deal['closes_on'] > $deal['opened_on'])->toBeTrue()
            ->and($deal['starts_on'] >= $deal['validated_on'])->toBeTrue();
    }
});

it('fills every working day to the standard: commessa hours first, the rest generic with no commessa', function () {
    foreach (['Attività', 'Riunione', 'Altro'] as $name) {
        TaskType::factory()->create(['name' => $name]);
    }
    $user = User::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $timesheet = new McTimesheet(FakerFactory::create('it_IT'));

    $timesheet->plan($workOrder->id, $workOrder->quote->opportunity->registry_id, [$user->id], '2025-03-03', '2025-03-07', 30 * 60, 'Edizione 1');
    $timesheet->flush([['user_id' => $user->id, 'hired_at' => '2025-03-03', 'delivery' => true, 'daily_minutes' => 480]]);

    $days = DB::table('time_entries')->where('user_id', $user->id)->whereBetween('date', ['2025-03-03', '2025-03-07'])
        ->selectRaw('date, SUM(minutes) AS total, SUM(CASE WHEN work_order_id IS NOT NULL THEN minutes ELSE 0 END) AS on_work_order')
        ->groupBy('date')->get();

    expect((int) $days->sum('on_work_order'))->toBe(30 * 60)
        ->and($days->every(fn (object $day): bool => (int) $day->total <= 480))->toBeTrue()
        ->and(DB::table('time_entries')->whereNull('work_order_id')->whereNotNull('registry_id')->exists())->toBeFalse()
        ->and(DB::table('time_entries')->where('date', '2025-03-08')->exists())->toBeFalse();
});

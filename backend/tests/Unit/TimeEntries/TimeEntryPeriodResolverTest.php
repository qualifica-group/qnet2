<?php

use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Services\TimeEntries\TimeEntryPeriodResolver;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| TimeEntryPeriodResolver (spec 0122, data_contract F "Periodo")
|--------------------------------------------------------------------------
| "Today" is pinned to a Monday (2026-09-14) via Carbon::setTestNow(), a
| known ISO week so week/month/year math is unambiguous to assert on.
*/

beforeEach(fn () => Carbon::setTestNow('2026-09-14 10:00:00')); // Monday
afterEach(fn () => Carbon::setTestNow());

function resolvePeriod(array $overrides = []): array
{
    $filter = TimeEntryFilterData::fromValidated($overrides);
    $period = (new TimeEntryPeriodResolver)->resolve($filter);

    return [$period->dateFrom, $period->dateTo];
}

it('date_from and date_to together win outright', function () {
    expect(resolvePeriod(['date_from' => '2026-01-01', 'date_to' => '2026-01-10']))
        ->toBe(['2026-01-01', '2026-01-10']);
});

it('decision: only date_from present collapses the period to that single day', function () {
    expect(resolvePeriod(['date_from' => '2026-03-05']))->toBe(['2026-03-05', '2026-03-05']);
});

it('decision: only date_to present collapses the period to that single day', function () {
    expect(resolvePeriod(['date_to' => '2026-03-05']))->toBe(['2026-03-05', '2026-03-05']);
});

it('period_preset=day resolves to today only', function () {
    expect(resolvePeriod(['period_preset' => 'day']))->toBe(['2026-09-14', '2026-09-14']);
});

it('period_preset=week resolves to the current ISO week, Monday..Sunday', function () {
    expect(resolvePeriod(['period_preset' => 'week']))->toBe(['2026-09-14', '2026-09-20']);
});

it('period_preset=month resolves to the current calendar month', function () {
    expect(resolvePeriod(['period_preset' => 'month']))->toBe(['2026-09-01', '2026-09-30']);
});

it('period_preset=year resolves to the current calendar year', function () {
    expect(resolvePeriod(['period_preset' => 'year']))->toBe(['2026-01-01', '2026-12-31']);
});

it('no dates and no preset falls back to the current week, same as period_preset=week', function () {
    expect(resolvePeriod([]))->toBe(['2026-09-14', '2026-09-20']);
});

<?php

use App\Enums\TimeEntryDailyStatus;

/*
|--------------------------------------------------------------------------
| TimeEntryDailyStatus::forTotals() (spec 0122, D-7)
|--------------------------------------------------------------------------
*/

it('AC-011/AC-012: a non-working day is always no_target, even with minutes logged', function () {
    expect(TimeEntryDailyStatus::forTotals(targetMinutes: 480, totalMinutes: 600, isNonWorkingDay: true))
        ->toBe(TimeEntryDailyStatus::NoTarget);
});

it('a zero target is no_target, even on a working day', function () {
    expect(TimeEntryDailyStatus::forTotals(targetMinutes: 0, totalMinutes: 30, isNonWorkingDay: false))
        ->toBe(TimeEntryDailyStatus::NoTarget);
});

it('AC-011: compares exactly to the minute, no tolerance', function () {
    expect(TimeEntryDailyStatus::forTotals(480, 479, false))->toBe(TimeEntryDailyStatus::UnderTarget)
        ->and(TimeEntryDailyStatus::forTotals(480, 480, false))->toBe(TimeEntryDailyStatus::OnTarget)
        ->and(TimeEntryDailyStatus::forTotals(480, 481, false))->toBe(TimeEntryDailyStatus::OverTarget);
});

it('values() lists the four DailyStatus strings for the daily_statuses[] filter allow-list', function () {
    expect(TimeEntryDailyStatus::values())->toBe(['no_target', 'under_target', 'on_target', 'over_target']);
});

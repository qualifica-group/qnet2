<?php

use App\Services\TimeEntries\WorkCalendar;

/*
|--------------------------------------------------------------------------
| WorkCalendar (spec 0122, D-7, AC-012)
|--------------------------------------------------------------------------
*/

it('flags the ten fixed civil/religious holidays regardless of the year', function () {
    $calendar = new WorkCalendar;

    foreach (['2025', '2026', '2027'] as $year) {
        foreach (['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'] as $monthDay) {
            expect($calendar->isHoliday("{$year}-{$monthDay}"))->toBeTrue("{$year}-{$monthDay} should be a holiday");
        }
    }
});

it('computes Easter Sunday/Monday from easter_days(), not a UTC timestamp (2025/2026/2027)', function () {
    $calendar = new WorkCalendar;

    // 2025: Easter 2025-04-20, Monday 2025-04-21.
    expect($calendar->isHoliday('2025-04-20'))->toBeTrue()
        ->and($calendar->isHoliday('2025-04-21'))->toBeTrue()
        ->and($calendar->isHoliday('2025-04-19'))->toBeFalse();

    // 2026: Easter 2026-04-05, Pasquetta 2026-04-06 (AC-012's own example).
    expect($calendar->isHoliday('2026-04-05'))->toBeTrue()
        ->and($calendar->isHoliday('2026-04-06'))->toBeTrue();

    // 2027: Easter 2027-03-28, Monday 2027-03-29.
    expect($calendar->isHoliday('2027-03-28'))->toBeTrue()
        ->and($calendar->isHoliday('2027-03-29'))->toBeTrue();
});

it('AC-012: 2026-04-04 (the Saturday before Pasquetta) is NOT a holiday, but is a non-working weekend day', function () {
    $calendar = new WorkCalendar;

    expect($calendar->isHoliday('2026-04-04'))->toBeFalse()
        ->and($calendar->isWeekend('2026-04-04'))->toBeTrue()
        ->and($calendar->isNonWorkingDay('2026-04-04'))->toBeTrue();
});

it('flags Saturday and Sunday as weekend, a Monday as not', function () {
    $calendar = new WorkCalendar;

    expect($calendar->isWeekend('2026-09-12'))->toBeTrue() // Saturday
        ->and($calendar->isWeekend('2026-09-13'))->toBeTrue() // Sunday
        ->and($calendar->isWeekend('2026-09-14'))->toBeFalse(); // Monday
});

it('a plain working weekday is neither a holiday nor non-working', function () {
    $calendar = new WorkCalendar;

    expect($calendar->isNonWorkingDay('2026-09-14'))->toBeFalse();
});

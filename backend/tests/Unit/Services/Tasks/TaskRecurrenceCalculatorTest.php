<?php

use App\DataObjects\Tasks\TaskRecurrenceData;
use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
use App\Services\Tasks\TaskRecurrenceCalculator;
use App\Services\TimeEntries\WorkCalendar;
use Carbon\CarbonImmutable;

if (! function_exists('taskRecurrenceCalculator')) {
    function taskRecurrenceCalculator(): TaskRecurrenceCalculator
    {
        return new TaskRecurrenceCalculator(new WorkCalendar);
    }
}

/*
|--------------------------------------------------------------------------
| TaskRecurrenceCalculator — pure calendar math (spec 0120, AC-001..AC-009;
| spec 0155, AC-001..AC-003 extend it with yearly/ordinal/custom/workdays_only)
|--------------------------------------------------------------------------
|
| No database, no RefreshDatabase: every date is a parameter (constraints).
*/

// REQUIREMENT CHANGED (spec 0155, D-1): the helper grows the five new
// fields the extended monthly/yearly/workdays_only shape needs, all
// defaulted so every pre-existing call site keeps its exact old meaning.
if (! function_exists('taskRecurrenceRule')) {
    function taskRecurrenceRule(
        TaskRecurrenceFrequency $frequency,
        int $interval = 1,
        ?array $weekdays = null,
        ?int $monthDay = null,
        TaskRecurrenceEnd $ends = TaskRecurrenceEnd::Never,
        ?string $endsOn = null,
        ?int $occurrenceCount = null,
        ?TaskRecurrenceMonthMode $monthMode = null,
        ?int $ordinal = null,
        ?int $ordinalWeekday = null,
        ?int $yearMonth = null,
        bool $workdaysOnly = false,
    ): TaskRecurrenceData {
        return new TaskRecurrenceData(
            frequency: $frequency,
            interval: $interval,
            weekdays: $weekdays,
            monthDay: $monthDay,
            monthMode: $monthMode,
            ordinal: $ordinal,
            ordinalWeekday: $ordinalWeekday,
            yearMonth: $yearMonth,
            workdaysOnly: $workdaysOnly,
            ends: $ends,
            endsOn: $endsOn,
            occurrenceCount: $occurrenceCount,
        );
    }
}

it('AC-001: daily, interval 3 from 01/03 produces 04/03, 07/03, 10/03', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, interval: 3);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 3);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-04', '2026-03-07', '2026-03-10']);
});

it('AC-002: weekly, interval 1, weekdays [1,3,5] from Monday 02/03 produces 04/03, 06/03, 09/03, 11/03', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Weekly, weekdays: [1, 3, 5]);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-02'), limit: 4);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-04', '2026-03-06', '2026-03-09', '2026-03-11']);
});

it('AC-003: weekly, interval 2, weekdays [2] spaces consecutive dates 14 days apart, all Tuesdays', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Weekly, interval: 2, weekdays: [2]);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-02'), limit: 2);

    expect((int) $dates[0]->diffInDays($dates[1]))->toBe(14)
        ->and($dates[0]->dayOfWeekIso)->toBe(2)
        ->and($dates[1]->dayOfWeekIso)->toBe(2);
});

it('AC-004: monthly, month_day 31 from 31/01/2027 clamps to 28/02, 31/03, 30/04 (D-2)', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Monthly, monthDay: 31);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2027-01-31'), limit: 3);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2027-02-28', '2027-03-31', '2027-04-30']);
});

it('AC-005: monthly, month_day 29 in a leap year produces the 29th of February, not the 28th', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Monthly, monthDay: 29);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2028-01-29'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2028-02-29');
});

it('AC-006: ends on_date caps generation at ends_on and never exceeds it', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, ends: TaskRecurrenceEnd::OnDate, endsOn: '2026-03-31');

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 1000);

    expect($dates)->toHaveCount(30)
        ->and(end($dates)->toDateString())->toBe('2026-03-31');
});

it('AC-007: ends after_count 5 produces 4 dates, the capostipite already counting as occurrence 1 (D-4)', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, ends: TaskRecurrenceEnd::AfterCount, occurrenceCount: 5);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 100, alreadyGenerated: 1);

    expect($dates)->toHaveCount(4);
});

it('AC-008: ends never produces as many dates as requested without ever declaring itself exhausted', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 50);

    expect($dates)->toHaveCount(50);
});

it('AC-009: interval 0 or negative is rejected with an exception instead of looping forever', function () {
    $zero = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, interval: 0);
    $negative = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, interval: -1);
    $calculator = taskRecurrenceCalculator();

    expect(fn () => $calculator->nextDates($zero, CarbonImmutable::parse('2026-03-01'), limit: 1))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calculator->nextDates($negative, CarbonImmutable::parse('2026-03-01'), limit: 1))
        ->toThrow(InvalidArgumentException::class);
});

/*
|--------------------------------------------------------------------------
| Spec 0155, D-1 — yearly/monthly ordinal, custom, workdays_only
|--------------------------------------------------------------------------
*/

it('AC-001 (spec 0155): yearly ordinal "2nd Tuesday of March" produces the right date', function () {
    $rule = taskRecurrenceRule(
        TaskRecurrenceFrequency::Yearly,
        monthMode: TaskRecurrenceMonthMode::Ordinal,
        ordinal: 2,
        ordinalWeekday: 2,
        yearMonth: 3,
    );

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-01-01'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2026-03-10');
});

it('AC-001 (spec 0155): monthly ordinal 5th Tuesday is skipped in a month without one, never moved', function () {
    // February 2027 has only 4 Tuesdays, March 2027 has 5 (30/03).
    $rule = taskRecurrenceRule(
        TaskRecurrenceFrequency::Monthly,
        monthMode: TaskRecurrenceMonthMode::Ordinal,
        ordinal: 5,
        ordinalWeekday: 2,
    );

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2027-01-01'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2027-03-30');
});

it('AC-002 (spec 0155): workdays_only never generates a Saturday or a Sunday', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, workdaysOnly: true);

    // REQUIREMENT CHANGED (spec 0160, D-1): a weekend candidate is now
    // SHIFTED onto the following Monday instead of skipped outright — but
    // for a plain daily rule the shifted Saturday/Sunday collapse (D-2) onto
    // that same Monday candidate, so the resulting set of distinct dates —
    // and this assertion — is unchanged: still exactly the 10 weekdays.
    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 10);

    expect($dates)->toHaveCount(10)
        ->and(collect($dates)->every(fn (CarbonImmutable $d) => ! $d->isWeekend()))->toBeTrue()
        ->and(end($dates)->toDateString())->toBe('2026-03-13');
});

it('AC-003 (spec 0155): custom with interval 3 generates every 3 days, same arithmetic as daily', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Custom, interval: 3);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 3);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-04', '2026-03-07', '2026-03-10']);
});

/*
|--------------------------------------------------------------------------
| Spec 0160, D-1..D-4 — workdays_only SHIFTS a non-working candidate
| forward instead of skipping it (REQUIREMENT CHANGED from spec 0155).
|--------------------------------------------------------------------------
*/

it('AC-001 (spec 0160): weekly on Saturday with workdays_only lands on the following Monday', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Weekly, weekdays: [6], workdaysOnly: true);

    // 2026-03-02 is a Monday; the first Saturday candidate is 2026-03-07.
    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-02'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2026-03-09')
        ->and($dates[0]->dayOfWeekIso)->toBe(1);
});

it('AC-002 (spec 0160): monthly on the 25th, April (fixed holiday) with workdays_only lands on the 26th', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Monthly, monthDay: 25, workdaysOnly: true);

    // 2029-04-25 is a Wednesday AND a fixed holiday (25/04); 2029-04-26 is
    // an ordinary working Thursday.
    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2029-03-25'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2029-04-26');
});

it('AC-003 (spec 0160): Pasquetta with workdays_only shifts to the Tuesday after', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, workdaysOnly: true);

    // 2026-04-06 is Easter Monday (Pasquetta); 2026-04-07 is an ordinary
    // working Tuesday.
    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-04-05'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2026-04-07');
});

it('AC-004 (spec 0160): daily workdays_only over a week produces 5 distinct Mon-Fri dates, no duplicates', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, workdaysOnly: true);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 5);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06'])
        ->and(array_unique(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates)))->toHaveCount(5);
});

it('AC-005 (spec 0160): "after 3 occurrences" counts 3 distinct dates even when Sat+Sun collapse onto Monday', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, workdaysOnly: true, ends: TaskRecurrenceEnd::AfterCount, occurrenceCount: 3);

    // From Thursday 2026-03-05: Fri 03-06 is occurrence #2, Sat/Sun both
    // collapse onto Mon 03-09 which is occurrence #3 — Tuesday 03-10 must
    // never be reached (the capostipite already counts as occurrence 1).
    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-05'), limit: 100, alreadyGenerated: 1);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-06', '2026-03-09']);
});

it('AC-006 (spec 0160): an occurrence shifted past the "ends on date" ceiling is not created', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, workdaysOnly: true, ends: TaskRecurrenceEnd::OnDate, endsOn: '2026-03-06');

    // Friday 03-06 is within the ceiling; Saturday 03-07 shifts to Monday
    // 03-09, past 03-06, so it must be refused.
    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-05'), limit: 100);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-06']);
});

it('AC-007 (spec 0160): without workdays_only, weekend/holiday dates are produced exactly as-is', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Weekly, weekdays: [6]);

    $dates = taskRecurrenceCalculator()->nextDates($rule, CarbonImmutable::parse('2026-03-02'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2026-03-07')
        ->and($dates[0]->dayOfWeekIso)->toBe(6);
});

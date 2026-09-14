<?php

use App\DataObjects\Tasks\TaskRecurrenceData;
use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Services\Tasks\TaskRecurrenceCalculator;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| TaskRecurrenceCalculator — pure calendar math (spec 0120, AC-001..AC-009)
|--------------------------------------------------------------------------
|
| No database, no RefreshDatabase: every date is a parameter (constraints).
*/

if (! function_exists('taskRecurrenceRule')) {
    function taskRecurrenceRule(
        TaskRecurrenceFrequency $frequency,
        int $interval = 1,
        ?array $weekdays = null,
        ?int $monthDay = null,
        TaskRecurrenceEnd $ends = TaskRecurrenceEnd::Never,
        ?string $endsOn = null,
        ?int $occurrenceCount = null,
    ): TaskRecurrenceData {
        return new TaskRecurrenceData($frequency, $interval, $weekdays, $monthDay, $ends, $endsOn, $occurrenceCount);
    }
}

it('AC-001: daily, interval 3 from 01/03 produces 04/03, 07/03, 10/03', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, interval: 3);

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 3);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-04', '2026-03-07', '2026-03-10']);
});

it('AC-002: weekly, interval 1, weekdays [1,3,5] from Monday 02/03 produces 04/03, 06/03, 09/03, 11/03', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Weekly, weekdays: [1, 3, 5]);

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2026-03-02'), limit: 4);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2026-03-04', '2026-03-06', '2026-03-09', '2026-03-11']);
});

it('AC-003: weekly, interval 2, weekdays [2] spaces consecutive dates 14 days apart, all Tuesdays', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Weekly, interval: 2, weekdays: [2]);

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2026-03-02'), limit: 2);

    expect((int) $dates[0]->diffInDays($dates[1]))->toBe(14)
        ->and($dates[0]->dayOfWeekIso)->toBe(2)
        ->and($dates[1]->dayOfWeekIso)->toBe(2);
});

it('AC-004: monthly, month_day 31 from 31/01/2027 clamps to 28/02, 31/03, 30/04 (D-2)', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Monthly, monthDay: 31);

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2027-01-31'), limit: 3);

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))
        ->toBe(['2027-02-28', '2027-03-31', '2027-04-30']);
});

it('AC-005: monthly, month_day 29 in a leap year produces the 29th of February, not the 28th', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Monthly, monthDay: 29);

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2028-01-29'), limit: 1);

    expect($dates[0]->toDateString())->toBe('2028-02-29');
});

it('AC-006: ends on_date caps generation at ends_on and never exceeds it', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, ends: TaskRecurrenceEnd::OnDate, endsOn: '2026-03-31');

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 1000);

    expect($dates)->toHaveCount(30)
        ->and(end($dates)->toDateString())->toBe('2026-03-31');
});

it('AC-007: ends after_count 5 produces 4 dates, the capostipite already counting as occurrence 1 (D-4)', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, ends: TaskRecurrenceEnd::AfterCount, occurrenceCount: 5);

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 100, alreadyGenerated: 1);

    expect($dates)->toHaveCount(4);
});

it('AC-008: ends never produces as many dates as requested without ever declaring itself exhausted', function () {
    $rule = taskRecurrenceRule(TaskRecurrenceFrequency::Daily);

    $dates = (new TaskRecurrenceCalculator)->nextDates($rule, CarbonImmutable::parse('2026-03-01'), limit: 50);

    expect($dates)->toHaveCount(50);
});

it('AC-009: interval 0 or negative is rejected with an exception instead of looping forever', function () {
    $zero = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, interval: 0);
    $negative = taskRecurrenceRule(TaskRecurrenceFrequency::Daily, interval: -1);
    $calculator = new TaskRecurrenceCalculator;

    expect(fn () => $calculator->nextDates($zero, CarbonImmutable::parse('2026-03-01'), limit: 1))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calculator->nextDates($negative, CarbonImmutable::parse('2026-03-01'), limit: 1))
        ->toThrow(InvalidArgumentException::class);
});

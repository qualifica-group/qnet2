<?php

use App\Services\Absences\Balances\AbsenceAccrualCalculator;
use Carbon\CarbonImmutable;

function accrualMonths(int $year, ?string $hired, ?string $terminated, string $today): int
{
    return (new AbsenceAccrualCalculator)->accruedMonths(
        $year,
        $hired ? CarbonImmutable::parse($hired) : null,
        $terminated ? CarbonImmutable::parse($terminated) : null,
        CarbonImmutable::parse($today),
    );
}

it('0278 D-10: months accrue only once started, past years fully, future years never', function () {
    expect(accrualMonths(2026, null, null, '2026-10-19'))->toBe(10)
        ->and(accrualMonths(2025, null, null, '2026-01-01'))->toBe(12)
        ->and(accrualMonths(2027, null, null, '2026-10-19'))->toBe(0);
});

it('0278 D-10: hired on the 10th of March counts March, on the 20th does not', function () {
    expect(accrualMonths(2026, '2026-03-10', null, '2026-10-19'))->toBe(8)
        ->and(accrualMonths(2026, '2026-03-20', null, '2026-10-19'))->toBe(7)
        ->and(accrualMonths(2026, '2026-03-17', null, '2026-10-19'))->toBe(8)
        ->and(accrualMonths(2026, '2026-03-18', null, '2026-10-19'))->toBe(7);
});

it('0278 D-10: a mid-month termination keeps the month only with 15 days in force', function () {
    expect(accrualMonths(2026, '2020-01-01', '2026-06-15', '2026-10-19'))->toBe(6)
        ->and(accrualMonths(2026, '2020-01-01', '2026-06-14', '2026-10-19'))->toBe(5)
        ->and(accrualMonths(2026, '2020-01-01', '2026-06-30', '2026-12-31'))->toBe(6);
});

it('0278 D-10: a person hired in a later year accrues nothing', function () {
    expect(accrualMonths(2026, '2027-01-01', null, '2026-10-19'))->toBe(0);
});

it('0278 D-10: accrued minutes are the rounded twelfths of the allowance', function () {
    $calculator = new AbsenceAccrualCalculator;

    expect($calculator->accruedMinutes(9600, 8))->toBe(6400)
        ->and($calculator->accruedMinutes(1000, 1))->toBe(83)
        ->and($calculator->accruedMinutes(0, 12))->toBe(0);
});

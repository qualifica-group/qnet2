<?php

use App\Enums\VatAllocation;
use App\Exceptions\Invoices\InvalidInstallmentConfigurationException;
use App\Models\PaymentMethod;
use App\Services\Invoices\InstallmentScheduleCalculator;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $attributes
 */
function schedule_method(array $attributes): PaymentMethod
{
    return new PaymentMethod($attributes + [
        'payment_method_code' => 'MP05',
        'payment_days' => 30,
        'installments_count' => 1,
        'days_between_installments' => 0,
        'end_of_month' => false,
        'end_of_month_extra_days' => null,
        'vat_allocation' => VatAllocation::Split,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $schedule
 * @return array<int, string>
 */
function schedule_amounts(array $schedule): array
{
    return array_column($schedule, 'amount');
}

/**
 * @param  array<int, array<string, mixed>>  $schedule
 * @return array<int, string>
 */
function schedule_dates(array $schedule): array
{
    return array_map(fn (array $row): string => $row['due_date']->toDateString(), $schedule);
}

it('splits the total in 3 installments with the remainder on the last one', function () {
    $schedule = (new InstallmentScheduleCalculator)->calculate(
        CarbonImmutable::parse('2026-01-10'),
        schedule_method(['installments_count' => 3, 'payment_days' => 30, 'days_between_installments' => 30]),
        '819.67', '180.33', '1000.00',
    );

    expect(schedule_amounts($schedule))->toBe(['333.33', '333.33', '333.34'])
        ->and(schedule_dates($schedule))->toBe(['2026-02-09', '2026-03-11', '2026-04-10'])
        ->and(array_column($schedule, 'sequence'))->toBe([1, 2, 3])
        ->and($schedule[0]['payment_method_code'])->toBe('MP05');
});

it('moves the due date to the end of the month and adds the extra days', function () {
    $calculator = new InstallmentScheduleCalculator;
    $date = CarbonImmutable::parse('2026-01-31');

    $fm = $calculator->calculate($date, schedule_method(['end_of_month' => true]), '100.00', '22.00', '122.00');
    $fmLeap = $calculator->calculate(CarbonImmutable::parse('2028-01-31'), schedule_method(['end_of_month' => true]), '100.00', '22.00', '122.00');
    $fmExtra = $calculator->calculate($date, schedule_method(['end_of_month' => true, 'end_of_month_extra_days' => 10]), '100.00', '22.00', '122.00');

    expect(schedule_dates($fm))->toBe(['2026-02-28'])
        ->and(schedule_dates($fmLeap))->toBe(['2028-02-29'])
        ->and(schedule_dates($fmExtra))->toBe(['2026-03-10']);
});

it('counts the end-of-month offset in months per installment', function () {
    $schedule = (new InstallmentScheduleCalculator)->calculate(
        CarbonImmutable::parse('2026-01-15'),
        schedule_method(['end_of_month' => true, 'installments_count' => 3, 'payment_days' => 60, 'days_between_installments' => 30]),
        '300.00', '0.00', '300.00',
    );

    expect(schedule_dates($schedule))->toBe(['2026-03-31', '2026-04-30', '2026-05-31']);
});

it('puts the whole VAT on the first or on the last installment', function () {
    $calculator = new InstallmentScheduleCalculator;
    $date = CarbonImmutable::parse('2026-01-10');

    $first = $calculator->calculate($date, schedule_method(['installments_count' => 3, 'vat_allocation' => VatAllocation::First]), '900.00', '198.00', '1098.00');
    $last = $calculator->calculate($date, schedule_method(['installments_count' => 3, 'vat_allocation' => VatAllocation::Last]), '900.00', '198.00', '1098.00');

    expect(schedule_amounts($first))->toBe(['498.00', '300.00', '300.00'])
        ->and(schedule_amounts($last))->toBe(['300.00', '300.00', '498.00']);
});

it('bills only the VAT on the first installment with vat_first and spreads the net on the rest', function () {
    $schedule = (new InstallmentScheduleCalculator)->calculate(
        CarbonImmutable::parse('2026-01-10'),
        schedule_method(['installments_count' => 3, 'vat_allocation' => VatAllocation::VatFirst]),
        '1000.01', '220.00', '1220.01',
    );

    expect(schedule_amounts($schedule))->toBe(['220.00', '500.00', '500.01']);
});

it('rejects vat_first with a single installment', function () {
    (new InstallmentScheduleCalculator)->calculate(
        CarbonImmutable::parse('2026-01-10'),
        schedule_method(['installments_count' => 1, 'vat_allocation' => VatAllocation::VatFirst]),
        '100.00', '22.00', '122.00',
    );
})->throws(InvalidInstallmentConfigurationException::class);

it('always sums the installments to the total', function (VatAllocation $allocation, int $count) {
    $schedule = (new InstallmentScheduleCalculator)->calculate(
        CarbonImmutable::parse('2026-03-05'),
        schedule_method(['installments_count' => $count, 'vat_allocation' => $allocation, 'days_between_installments' => 15]),
        '1234.57', '271.60', '1506.17',
    );

    expect(array_reduce($schedule, fn (string $sum, array $row): string => bcadd($sum, $row['amount'], 2), '0.00'))->toBe('1506.17')
        ->and($schedule)->toHaveCount($count);
})->with(function () {
    foreach ([2, 3, 7] as $count) {
        foreach (VatAllocation::cases() as $allocation) {
            yield "{$allocation->value} x{$count}" => [$allocation, $count];
        }
    }
});

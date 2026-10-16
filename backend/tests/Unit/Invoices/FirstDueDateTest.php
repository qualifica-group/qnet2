<?php

use App\Enums\VatAllocation;
use App\Models\PaymentMethod;
use App\Services\Invoices\InstallmentScheduleCalculator;
use Carbon\CarbonImmutable;

// Spec 0270, AC-004 — firstDueDate() is the first due date of calculate().
it('returns the first due date calculate() produces, with and without end of month', function (array $attributes, string $date, string $expected) {
    $method = new PaymentMethod($attributes + [
        'payment_method_code' => 'MP05', 'installments_count' => 3, 'days_between_installments' => 30,
        'end_of_month' => false, 'end_of_month_extra_days' => null, 'vat_allocation' => VatAllocation::Split,
    ]);
    $calculator = new InstallmentScheduleCalculator;
    $documentDate = CarbonImmutable::parse($date);

    $first = $calculator->firstDueDate($documentDate, $method);

    expect($first->toDateString())->toBe($expected)
        ->and($first->toDateString())->toBe($calculator->calculate($documentDate, $method, '100.00', '22.00', '122.00')[0]['due_date']->toDateString());
})->with([
    '30 days' => [['payment_days' => 30], '2026-01-10', '2026-02-09'],
    'immediate' => [['payment_days' => 0], '2026-01-10', '2026-01-10'],
    'end of month' => [['payment_days' => 30, 'end_of_month' => true], '2026-01-31', '2026-02-28'],
    'end of month plus extra days' => [['payment_days' => 60, 'end_of_month' => true, 'end_of_month_extra_days' => 5], '2026-01-10', '2026-04-05'],
]);

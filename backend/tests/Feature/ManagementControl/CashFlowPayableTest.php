<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\PaymentMethod;
use App\Models\VatRate;
use App\Services\Invoices\InstallmentScheduleCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0270, AC-004 — payable installments and committed order lines by expected month.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-004: the payable installments follow their due date', function () {
    mcPayable([['due' => -5, 'amount' => '10.00'], ['due' => 0, 'amount' => '20.00'], ['due' => mcMonthDue(1), 'amount' => '30.00'], ['due' => 4, 'amount' => '99.00', 'paid' => '99.00']]);

    $data = mcGet('cash-flow')->assertOk()->json('data');

    expect($data['backlog']['payable'])->toEqual(10)
        ->and(array_column($data['months'], 'payable'))->toEqual([20, 30, 0, 0, 0, 0]);
});

it('AC-004: the committed lines land in the month of the first due date of the order payment method', function () {
    $thirtyDays = PaymentMethod::factory()->create(['installments_count' => 1, 'payment_days' => 30, 'days_between_installments' => 0, 'end_of_month' => false]);
    $endOfMonth = PaymentMethod::factory()->create(['installments_count' => 2, 'payment_days' => 60, 'days_between_installments' => 30, 'end_of_month' => true, 'end_of_month_extra_days' => 0]);
    $today = CarbonImmutable::today();
    $calculator = new InstallmentScheduleCalculator;

    mcOrderLine(null, '100.00', order: ['payment_method_id' => $thirtyDays->id, 'order_date' => $today->toDateString()]);
    mcOrderLine(null, '200.00', order: ['payment_method_id' => $endOfMonth->id, 'order_date' => $today->toDateString()]);
    mcOrderLine(null, '300.00', order: ['order_date' => $today->toDateString()]);
    mcOrderLine(null, '400.00', order: ['order_date' => $today->subDays(3)->toDateString()]);
    mcOrderLine(null, '500.00', PurchaseOrderStatus::Draft, ['order_date' => $today->toDateString()]);
    $partial = mcOrderLine(null, '10.00', order: ['order_date' => $today->toDateString()], line: ['quantity' => '3.000']);
    mcPayableLine(null, '20.00', $partial, '2.000');

    $data = mcGet('cash-flow', ['horizon_months' => 24])->assertOk()->json('data');

    $thirtyMonth = $calculator->firstDueDate($today, $thirtyDays)->format('Y-m');
    $endOfMonthMonth = $calculator->firstDueDate($today, $endOfMonth)->format('Y-m');
    $expected = array_fill_keys(array_column($data['months'], 'month'), 0);
    foreach ([[$thirtyMonth, 100], [$endOfMonthMonth, 200], [mcMonth(0), 310]] as [$month, $amount]) {
        $expected[$month] += $amount;
    }

    expect($thirtyMonth)->toBe($today->addDays(30)->format('Y-m'))
        ->and($endOfMonthMonth)->toBe($calculator->calculate($today, $endOfMonth, '100.00', '0.00', '100.00')[0]['due_date']->format('Y-m'))
        ->and($data['backlog']['committed'])->toEqual(400)
        ->and(array_column($data['months'], 'committed', 'month'))->toEqual($expected);
});

it('AC-004 / D-15: the committed lines count VAT included at the line rate, on the residual too', function () {
    $standard = VatRate::factory()->create(['rate' => '22.00']);
    $today = CarbonImmutable::today()->toDateString();

    mcOrderLine(null, '100.00', order: ['order_date' => $today], line: ['vat_rate_id' => $standard->id]);
    mcOrderLine(null, '50.00', order: ['order_date' => $today]);
    $partial = mcOrderLine(null, '10.01', order: ['order_date' => $today], line: ['quantity' => '3.000', 'vat_rate_id' => $standard->id]);
    mcPayableLine(null, '20.02', $partial, '2.000');

    $data = mcGet('cash-flow')->assertOk()->json('data');

    // 100 + 22 VAT, 50 without rate, residual 10.01 + 2.20 VAT
    expect($data['months'][0]['committed'])->toEqual(184.21);
});

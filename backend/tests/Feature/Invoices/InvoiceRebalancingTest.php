<?php

use App\Models\InvoiceInstallment;
use App\Models\PaymentMethod;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const REBALANCE_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete', 'collect'];

/**
 * An issued 1200.00 invoice on 3 x 400.00 with the first installment collected.
 *
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function rebalanceInvoice(string $total = '1200.00'): array
{
    [$invoice, $payload] = invoiceWithPlan($total);
    collectInstallment($invoice['installments'][0]['id'], '400')->assertOk();

    return [$invoice, $payload];
}

it('AC-001: a lower total rebalances the open installments and keeps their due dates', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = rebalanceInvoice();

    $response = $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1000.00'))
        ->assertOk()->assertJsonPath('data.total_amount', '1000.00');

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:300.00', '3:300.00'])
        ->and(array_column($response->json('data.installments'), 'due_date'))->toBe(array_column($invoice['installments'], 'due_date'))
        ->and($response->json('data.installments.0.status'))->toBe('paid');
});

it('AC-002: a higher total is spread on the open installments', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = rebalanceInvoice();

    $response = $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1300.00'))->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:450.00', '3:450.00']);
});

it('AC-003: an indivisible residual puts the remainder on the last open installment and the sum matches the total', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = rebalanceInvoice();

    $response = $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1000.01'))->assertOk();
    $amounts = array_column($response->json('data.installments'), 'amount');

    expect($amounts)->toBe(['400.00', '300.00', '300.01'])
        ->and(array_reduce($amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0'))->toBe('1000.01');
});

it('AC-004: a total lower than the collected amount is a 422 on lines and nothing changes', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = rebalanceInvoice();

    $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '300.00'))
        ->assertUnprocessable()->assertJsonValidationErrors(['lines' => 'The document total cannot be lower than the amount already collected.']);

    $after = $this->getJson("/api/invoices/{$invoice['id']}")->assertOk();
    expect($after->json('data.total_amount'))->toBe('1200.00')
        ->and(invoicePlan($after->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and($after->json('data.lines.0.unit_price'))->toBe($invoice['lines'][0]['unit_price']);
});

it('AC-005: a total equal to the collected amount drops the open installments and the invoice is paid', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = rebalanceInvoice();

    $response = $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '400.00'))->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00'])
        ->and($response->json('data.payment_status'))->toBe('paid');
});

it('AC-006: with every installment collected a higher total adds one installment 30 days after the last', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = invoiceWithPlan();

    foreach ($invoice['installments'] as $installment) {
        collectInstallment($installment['id'], '400')->assertOk();
    }

    $response = $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1300.00'))->assertOk();
    $installments = $response->json('data.installments');

    expect(invoicePlan($installments))->toBe(['1:400.00', '2:400.00', '3:400.00', '4:100.00'])
        ->and(substr($installments[3]['due_date'], 0, 10))->toBe('2026-07-13')
        ->and($installments[3]['collected_amount'])->toBeNull();
});

it('AC-007: a legacy partially paid installment is closed at what was collected and the rest joins the residual', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = invoiceWithPlan();
    InvoiceInstallment::query()->whereKey($invoice['installments'][0]['id'])->update(['collected_amount' => '200.00', 'collected_at' => '2026-04-20']);

    $response = $this->putJson("/api/invoices/{$invoice['id']}", $payload)->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:200.00', '2:500.00', '3:500.00'])
        ->and($response->json('data.installments.0.status'))->toBe('paid');
});

it('AC-008: with collections the date, payment method and customer are frozen (422 per field) and nothing changes', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice, $payload] = rebalanceInvoice();
    $changes = [
        'document_date' => '2026-04-01',
        'payment_method_id' => PaymentMethod::factory()->create()->id,
        'customer_registry_id' => Registry::factory()->create()->id,
    ];

    foreach ($changes as $field => $value) {
        $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice([...$payload, $field => $value], '1000.00'))
            ->assertUnprocessable()->assertJsonValidationErrors([$field => 'This field cannot be changed once installments have been collected.']);
    }

    $after = $this->getJson("/api/invoices/{$invoice['id']}")->assertOk();
    expect($after->json('data.total_amount'))->toBe('1200.00')
        ->and(invoicePlan($after->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:400.00']);
});

it('AC-009: with collections the VAT allocation of the method is ignored and the residual is split in equal parts', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    // net 1000 + 22% VAT, VAT entirely on the last installment: 333.33 / 333.33 / 553.34
    [$invoice, $payload] = invoiceWithPlan('1000.00', 3, '22.00', ['vat_allocation' => 'last']);
    expect(array_column($invoice['installments'], 'amount'))->toBe(['333.33', '333.33', '553.34']);
    collectInstallment($invoice['installments'][0]['id'], '333.33')->assertOk();

    $response = $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1300.00'))->assertOk();

    // total 1586.00, residual 1252.67 in two parts
    expect(array_column($response->json('data.installments'), 'amount'))->toBe(['333.33', '626.33', '626.34']);
});

it('AC-021: the preview with invoice_id returns the rebalanced plan with locked rows and writes nothing', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice] = rebalanceInvoice();
    $before = InvoiceInstallment::query()->orderBy('id')->get()->toArray();
    $body = ['invoice_id' => $invoice['id'], 'document_date' => '2026-03-15', 'payment_method_id' => $invoice['payment_method']['id'], 'net_amount' => '1000', 'vat_amount' => '0', 'total_amount' => '1000'];

    $rows = $this->postJson('/api/invoices/installment-preview', $body)->assertOk()->json('data');

    expect(array_column($rows, 'amount'))->toBe(['400.00', '300.00', '300.00'])
        ->and(array_column($rows, 'locked'))->toBe([true, false, false])
        ->and(array_column($rows, 'collected_amount'))->toBe(['400.00', null, null])
        ->and(array_column($rows, 'due_date'))->toBe(array_map(fn (array $row): string => substr($row['due_date'], 0, 10), $invoice['installments']))
        ->and(InvoiceInstallment::query()->orderBy('id')->get()->toArray())->toBe($before);

    $this->postJson('/api/invoices/installment-preview', [...$body, 'total_amount' => '300'])
        ->assertUnprocessable()->assertJsonValidationErrors(['total_amount' => 'The document total cannot be lower than the amount already collected.']);
});

it('AC-021: the preview without incassi or without invoice_id keeps the plain schedule with the new fields', function () {
    Sanctum::actingAs(invoiceUserWith(REBALANCE_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $body = ['document_date' => '2026-03-15', 'payment_method_id' => $invoice['payment_method']['id'], 'net_amount' => '600', 'vat_amount' => '0', 'total_amount' => '600'];

    foreach ([$body, [...$body, 'invoice_id' => $invoice['id']]] as $payload) {
        $rows = $this->postJson('/api/invoices/installment-preview', $payload)->assertOk()->json('data');

        expect(array_column($rows, 'amount'))->toBe(['200.00', '200.00', '200.00'])
            ->and(array_column($rows, 'locked'))->toBe([false, false, false])
            ->and(array_column($rows, 'collected_amount'))->toBe([null, null, null]);
    }

    $this->postJson('/api/invoices/installment-preview', [...$body, 'invoice_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('invoice_id');
});

it('AC-020: without invoices.update the invoice PUT and the preview with invoice_id are 403', function () {
    $admin = invoiceUserWith(REBALANCE_ABILITIES);
    $withoutUpdate = invoiceUserWith(['viewAny', 'view', 'create', 'delete', 'collect']);
    Sanctum::actingAs($admin);
    [$invoice, $payload] = rebalanceInvoice();
    $body = ['invoice_id' => $invoice['id'], 'document_date' => '2026-03-15', 'payment_method_id' => $invoice['payment_method']['id'], 'net_amount' => '1000', 'vat_amount' => '0', 'total_amount' => '1000'];

    Sanctum::actingAs($withoutUpdate);

    $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1000.00'))->assertForbidden();
    $this->postJson('/api/invoices/installment-preview', $body)->assertForbidden();
    $this->postJson('/api/invoices/installment-preview', [...$body, 'invoice_id' => null])->assertOk();
});

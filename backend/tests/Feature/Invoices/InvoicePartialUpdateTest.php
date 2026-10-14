<?php

declare(strict_types=1);

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0261, D-1: PATCH /invoices/{invoice} edits one header field; what is
// not sent keeps its stored value (lines included), then the same full
// update runs (schedule, rebalancing, commissions).

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(invoiceUserWith(['view', 'create', 'update', 'collect'])));

it('AC-001: patches the notes alone and keeps lines and schedule', function () {
    [$data] = invoiceWithPlan('1200.00', 3);

    $response = $this->patchJson("/api/invoices/{$data['id']}", ['notes' => 'Bonifico a 30 giorni'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Bonifico a 30 giorni')
        ->assertJsonPath('data.total_amount', $data['total_amount']);

    expect($response->json('data.lines'))->toHaveCount(count($data['lines']))
        ->and($response->json('data.installments'))->toHaveCount(3);
});

it('AC-001: a new payment method recomputes the schedule', function () {
    [$data] = invoiceWithPlan('1200.00', 3);
    $single = PaymentMethod::factory()->create(['installments_count' => 1, 'payment_days' => 0, 'days_between_installments' => 0]);

    $this->patchJson("/api/invoices/{$data['id']}", ['payment_method_id' => $single->id])
        ->assertOk()
        ->assertJsonPath('data.payment_method.id', $single->id)
        ->assertJsonCount(1, 'data.installments');
});

it('AC-001: a stored payment method deactivated since does not block another field', function () {
    [$data] = invoiceWithPlan('1200.00', 3);
    PaymentMethod::query()->whereKey($data['payment_method']['id'])->update(['is_active' => false]);

    $this->patchJson("/api/invoices/{$data['id']}", ['notes' => 'Ok'])->assertOk();
});

it('AC-002: collected installments freeze the document date', function () {
    [$data] = invoiceWithPlan('1200.00', 3);
    collectInstallment($data['installments'][0]['id'], '400.00')->assertOk();

    $this->patchJson("/api/invoices/{$data['id']}", ['document_date' => '2026-04-01'])->assertUnprocessable();
    $this->patchJson("/api/invoices/{$data['id']}", ['notes' => 'Still editable'])->assertOk();
});

it('AC-002: a registered invoice refuses the header patch', function () {
    [$data] = invoiceWithPlan('1200.00', 3);
    Invoice::query()->whereKey($data['id'])->update(['type' => InvoiceType::Invoice, 'external_number' => '7', 'external_date' => '2026-03-20']);

    $this->patchJson("/api/invoices/{$data['id']}", ['notes' => 'Late'])->assertStatus(409);
});

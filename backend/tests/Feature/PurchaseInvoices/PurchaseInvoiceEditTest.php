<?php

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('AC-007: editing a proforma without payments replaces the lines and recomputes the schedule', function () {
    $document = createPurchaseInvoice();
    $before = $document->installments()->pluck('id')->all();

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, [
        'document_date' => '2026-05-01',
        'lines' => [[
            'description' => 'Only line',
            'quantity' => '4',
            'unit_price' => '50.00',
            'cost_type_id' => $document->lines()->value('cost_type_id'),
        ]],
    ]))->assertOk()
        ->assertJsonPath('data.total_amount', '200.00')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonCount(2, 'data.installments');

    expect($document->refresh()->installments()->pluck('due_date')->map->toDateString()->all())->toBe(['2026-05-31', '2026-06-30'])
        ->and((float) $document->installments()->sum('amount'))->toBe(200.0)
        ->and($document->installments()->pluck('id')->all())->not->toBe($before)
        ->and($document->lines()->count())->toBe(1);
});

it('AC-007: the supplier and the company cannot be changed', function () {
    $document = createPurchaseInvoice();

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['supplier_id' => Registry::factory()->supplier()->create()->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['company_id' => Company::factory()->create()->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('company_id');
});

it('AC-007: with payments the date and method are frozen and the open installments are rebalanced', function () {
    $document = createPurchaseInvoice();
    $first = $document->installments()->first();
    $first->update(['paid_amount' => $first->amount, 'paid_at' => '2026-04-14']);
    $paid = (string) $first->amount;

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['document_date' => '2026-06-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_date');
    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['payment_method_id' => PaymentMethod::factory()->create()->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['lines' => [[
        'description' => 'Bigger',
        'quantity' => '1',
        'unit_price' => '1000.00',
        'cost_type_id' => $document->lines()->value('cost_type_id'),
    ]]]))->assertOk()
        ->assertJsonPath('data.total_amount', '1000.00')
        ->assertJsonPath('data.has_payments', true)
        ->assertJsonPath('data.paid_amount', $paid);

    $rows = $document->refresh()->installments()->get();

    expect((float) $rows->sum('amount'))->toBe(1000.0)
        ->and((string) $rows->first()->amount)->toBe($paid)
        ->and((string) $rows->first()->paid_amount)->toBe($paid);
});

it('AC-007: the total cannot drop below what was paid', function () {
    $document = createPurchaseInvoice();
    $first = $document->installments()->first();
    $first->update(['paid_amount' => $first->amount, 'paid_at' => '2026-04-14']);

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['lines' => [[
        'description' => 'Tiny',
        'quantity' => '1',
        'unit_price' => '1.00',
        'cost_type_id' => $document->lines()->value('cost_type_id'),
    ]]]))->assertUnprocessable()->assertJsonValidationErrors('lines');
});

it('AC-007: a document with payments cannot be deleted', function () {
    $document = createPurchaseInvoice();
    $document->installments()->first()->update(['paid_amount' => '10.00', 'paid_at' => '2026-04-14']);

    $this->deleteJson("/api/purchase-invoices/{$document->id}")
        ->assertStatus(409)->assertJsonPath('message', __('purchase_invoices.has_payments'));

    expect(PurchaseInvoice::query()->count())->toBe(1)
        ->and($document->lines()->count())->toBe(2)
        ->and($this->getJson("/api/purchase-invoices/{$document->id}")->json('permissions.actions.delete'))->toBeFalse();
});

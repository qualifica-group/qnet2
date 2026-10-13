<?php

use App\Models\Company;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseInvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const PURCHASE_INVOICE_WRITER_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

it('AC-002: creates a document from scratch with bcmath lines, header = sum and the schedule of the method', function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(PURCHASE_INVOICE_WRITER_ABILITIES));
    $payload = purchaseInvoicePayload();

    $response = $this->postJson('/api/purchase-invoices', $payload)->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.document_type', 'proforma')
        ->assertJsonPath('data.net_amount', '262.35')
        ->assertJsonPath('data.vat_amount', '55.00')
        ->assertJsonPath('data.total_amount', '317.35')
        ->assertJsonPath('data.paid_amount', '0.00')
        ->assertJsonPath('data.residual_amount', '317.35')
        ->assertJsonPath('data.has_payments', false)
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonCount(2, 'data.installments');

    $lines = $response->json('data.lines');

    expect($lines[0]['net_amount'])->toBe('250.00')
        ->and($lines[0]['vat_amount'])->toBe('55.00')
        ->and($lines[0]['total_amount'])->toBe('305.00')
        ->and($lines[1]['quantity'])->toBe('1.235')
        ->and($lines[1]['net_amount'])->toBe('12.35')
        ->and($lines[1]['vat_amount'])->toBe('0.00')
        ->and($lines[1]['vat_rate'])->toBeNull()
        ->and($lines[0]['cost_type']['id'])->not->toBe($lines[1]['cost_type']['id'])
        ->and($lines[0]['work_order']['id'])->not->toBe($lines[1]['work_order']['id'])
        ->and($lines[0]['sort_order'])->toBe(0);

    $document = PurchaseInvoice::query()->findOrFail($response->json('data.id'));

    expect($document->lines()->count())->toBe(2)
        ->and(PurchaseInvoiceLine::query()->sum('net_amount'))->toEqual('262.35')
        ->and((string) $document->installments()->sum('amount'))->toBe('317.35')
        ->and($document->installments()->pluck('due_date')->map->toDateString()->all())->toBe(['2026-04-14', '2026-05-14']);
});

it('AC-002: the response carries the permissions block of the actor', function () {
    Sanctum::actingAs(purchaseInvoiceUserWith([...PURCHASE_INVOICE_WRITER_ABILITIES, 'pay']));

    $this->postJson('/api/purchase-invoices', purchaseInvoicePayload())->assertCreated()
        ->assertJsonPath('permissions.actions.update', true)
        ->assertJsonPath('permissions.actions.delete', true)
        ->assertJsonPath('permissions.actions.details', true)
        ->assertJsonPath('permissions.actions.downgrade', false)
        ->assertJsonPath('permissions.actions.view_activity', false)
        ->assertJsonPath('permissions.actions.pay', true);
});

it('shows the detail of a document and answers 404 for a missing one', function () {
    $document = createPurchaseInvoice();

    $this->getJson("/api/purchase-invoices/{$document->id}")->assertOk()
        ->assertJsonPath('data.id', $document->id)
        ->assertJsonPath('data.supplier.id', $document->supplier_id)
        ->assertJsonPath('data.created_by.id', auth()->id())
        ->assertJsonCount(2, 'data.lines');

    $this->getJson('/api/purchase-invoices/999999')->assertNotFound();
});

it('validates the payload: lines are required and an invoice needs its number', function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(PURCHASE_INVOICE_WRITER_ABILITIES));

    $this->postJson('/api/purchase-invoices', purchaseInvoicePayload(['lines' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors('lines');
    $this->postJson('/api/purchase-invoices', purchaseInvoicePayload(['document_type' => 'invoice', 'document_number' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');
    $this->postJson('/api/purchase-invoices', purchaseInvoicePayload(['document_type' => 'bogus']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_type');

    expect(PurchaseInvoice::query()->count())->toBe(0);
});

it('refuses a zero total and a bank account of another company', function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(PURCHASE_INVOICE_WRITER_ABILITIES));
    $payload = purchaseInvoicePayload();
    $free = array_map(fn (array $line): array => [...$line, 'unit_price' => '0'], $payload['lines']);

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => $free])
        ->assertUnprocessable()->assertJsonValidationErrors('lines');
    $this->postJson('/api/purchase-invoices', [...$payload, 'financial_account_id' => companyBankAccount(Company::factory()->create()->id)->id])
        ->assertUnprocessable()->assertJsonValidationErrors('financial_account_id');
    $this->postJson('/api/purchase-invoices', [...$payload, 'financial_account_id' => companyBankAccount($payload['company_id'])->id])
        ->assertCreated();
});

it('AC-008: every endpoint answers 403 without its ability', function () {
    $document = createPurchaseInvoice();
    $payload = purchaseInvoicePayload();

    Sanctum::actingAs(purchaseInvoiceUserWith([]));

    $this->postJson('/api/purchase-invoices', $payload)->assertForbidden();
    $this->getJson("/api/purchase-invoices/{$document->id}")->assertForbidden();
    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document))->assertForbidden();
    $this->patchJson("/api/purchase-invoices/{$document->id}/details", ['notes' => 'x'])->assertForbidden();
    $this->deleteJson("/api/purchase-invoices/{$document->id}")->assertForbidden();
    $this->postJson('/api/purchase-invoices/installment-preview', ['document_date' => '2026-03-15', 'payment_method_id' => $document->payment_method_id, 'net_amount' => 100, 'vat_amount' => 0, 'total_amount' => 100])->assertForbidden();

    Sanctum::actingAs(purchaseInvoiceUserWith(['view', 'create']));
    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document))->assertForbidden();
    $this->deleteJson("/api/purchase-invoices/{$document->id}")->assertForbidden();

    Sanctum::actingAs(purchaseInvoiceUserWith(['view', 'update']));
    $this->postJson('/api/purchase-invoices', $payload)->assertForbidden();
    $this->deleteJson("/api/purchase-invoices/{$document->id}")->assertForbidden();

    expect(PurchaseInvoice::query()->count())->toBe(1);
});

it('previews the schedule of the amounts for the editor, with create or update ability', function () {
    $document = createPurchaseInvoice();
    Sanctum::actingAs(purchaseInvoiceUserWith(['create']));
    $body = ['document_date' => '2026-03-15', 'payment_method_id' => $document->payment_method_id, 'net_amount' => '100.00', 'vat_amount' => '22.00', 'total_amount' => '122.00'];

    $rows = $this->postJson('/api/purchase-invoices/installment-preview', $body)->assertOk()->json('data');

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['sequence' => 1, 'due_date' => '2026-04-14', 'amount' => '61.00', 'paid_amount' => null, 'locked' => false]);

    Sanctum::actingAs(purchaseInvoiceUserWith(['update']));
    $this->postJson('/api/purchase-invoices/installment-preview', [...$body, 'purchase_invoice_id' => $document->id])->assertOk();

    Sanctum::actingAs(purchaseInvoiceUserWith(['create']));
    $this->postJson('/api/purchase-invoices/installment-preview', [...$body, 'purchase_invoice_id' => $document->id])->assertForbidden();
});

it('deletes a proforma with lines, schedule, order links', function () {
    $document = createPurchaseInvoice();

    $this->deleteJson("/api/purchase-invoices/{$document->id}")->assertNoContent();

    expect(PurchaseInvoice::query()->count())->toBe(0)
        ->and(PurchaseInvoiceLine::query()->count())->toBe(0)
        ->and(PurchaseInvoiceInstallment::query()->count())->toBe(0);
});

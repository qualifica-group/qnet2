<?php

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\FinancialAccount;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function approveUrl(CommissionSettlement $settlement): string
{
    return "/api/commission-settlements/{$settlement->id}/approve";
}

it('AC-006: approving creates the linked payable invoice and moves the settlement to APPROVED', function () {
    $settlement = approvableSettlement(['document_number' => 'FA-900', 'document_date' => '2026-10-02']);
    $actor = approvalUser();
    Sanctum::actingAs($actor);
    $choices = approvalChoices();

    $this->postJson(approveUrl($settlement), $choices)
        ->assertOk()
        ->assertJsonPath('data.status', 'APPROVED')
        ->assertJsonPath('data.approved_by.id', $actor->id)
        ->assertJsonPath('data.purchase_invoice.document_number', 'FA-900')
        ->assertJsonPath('data.purchase_invoice.payable_amount', '1020.00')
        ->assertJsonPath('data.purchase_invoice.paid_amount', '0.00')
        ->assertJsonPath('data.purchase_invoice.residual_amount', '1020.00')
        ->assertJsonPath('data.purchase_invoice.payment_status', 'not_due')
        ->assertJsonPath('data.abilities.approve', false);

    $fresh = $settlement->fresh();
    $invoice = PurchaseInvoice::query()->with(['lines', 'installments'])->findOrFail($fresh->purchase_invoice_id);

    expect($fresh->approved_at)->not->toBeNull()
        ->and($invoice->document_type->value)->toBe('invoice')
        ->and($invoice->company_id)->toBe($settlement->company_id)
        ->and($invoice->supplier_id)->toBe((int) $settlement->referent->supplier_registry_id)
        ->and($invoice->document_date->toDateString())->toBe('2026-10-02')
        ->and($invoice->withholding_amount)->toBe('115.00')
        ->and($invoice->enasarco_amount)->toBe('85.00')
        ->and($invoice->total_amount)->toBe('1220.00')
        ->and($invoice->notes)->toContain($settlement->code())
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines[0]->unit_price)->toEqual('1000.00')
        ->and($invoice->lines[0]->cost_type_id)->toBe($choices['cost_type_id'])
        ->and($invoice->lines[0]->vat_rate_id)->toBe($choices['vat_rate_id'])
        ->and(array_reduce($invoice->installments->all(), fn (string $sum, $row): string => bcadd($sum, (string) $row->amount, 2), '0.00'))->toBe('1020.00');
});

it('AC-006: an error of the invoice leaves the settlement DOCUMENT_RECEIVED and creates nothing', function () {
    $settlement = approvableSettlement();
    Sanctum::actingAs(approvalUser());
    $before = PurchaseInvoice::query()->count();

    $this->postJson(approveUrl($settlement), approvalChoices(['financial_account_id' => FinancialAccount::factory()->create()->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('financial_account_id');

    expect($settlement->fresh()->status)->toBe(CommissionSettlementStatus::DocumentReceived)
        ->and($settlement->fresh()->purchase_invoice_id)->toBeNull()
        ->and(PurchaseInvoice::query()->count())->toBe($before);
});

it('AC-007: without a supplier the approval is a 422 on supplier', function () {
    $settlement = amountedSettlement('document_received');
    Sanctum::actingAs(approvalUser());

    $this->postJson(approveUrl($settlement), approvalChoices())->assertUnprocessable()->assertJsonValidationErrors('supplier');
});

it('AC-007: a VAT of the line outside the tolerance of the document is a 422 on vat_rate_id', function () {
    $settlement = approvableSettlement();
    Sanctum::actingAs(approvalUser());

    $this->postJson(approveUrl($settlement), approvalChoices(['vat_rate_id' => VatRate::factory()->create(['rate' => '10.00'])->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('vat_rate_id');

    expect($settlement->fresh()->status)->toBe(CommissionSettlementStatus::DocumentReceived);
});

it('AC-007: a number already used for the supplier and year is a 422 on document_number', function () {
    $settlement = approvableSettlement(['document_number' => 'FA-DUP', 'document_date' => '2026-10-02']);
    Sanctum::actingAs(approvalUser());
    $this->postJson(approveUrl($settlement), approvalChoices())->assertOk();

    $other = amountedSettlement('document_received', ['document_number' => 'FA-DUP', 'document_date' => '2026-10-03']);
    $other->referent->update(['supplier_registry_id' => $settlement->referent->supplier_registry_id]);

    $this->postJson(approveUrl($other), approvalChoices())->assertUnprocessable()->assertJsonValidationErrors('document_number');

    expect($other->fresh()->status)->toBe(CommissionSettlementStatus::DocumentReceived);
});

it('AC-007: an inactive payment method is a 422 on payment_method_id', function () {
    $settlement = approvableSettlement();
    Sanctum::actingAs(approvalUser());

    $this->postJson(approveUrl($settlement), approvalChoices(['payment_method_id' => PaymentMethod::factory()->create(['is_active' => false])->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
});

it('AC-007: any state but DOCUMENT_RECEIVED is a 409', function (string $state) {
    $settlement = amountedSettlement($state);
    $settlement->referent->update(['supplier_registry_id' => Registry::factory()->supplier()->create()->id]);
    Sanctum::actingAs(approvalUser());

    $this->postJson(approveUrl($settlement), approvalChoices())->assertStatus(409);
})->with(['draft', 'issued', 'approved', 'cancelled']);

it('AC-007: the approval needs both the approve and the purchase-invoices.create permission', function (string $missing) {
    $settlement = approvableSettlement();
    Sanctum::actingAs(approvalUser([$missing]));

    $this->postJson(approveUrl($settlement), approvalChoices())->assertForbidden();

    expect($settlement->fresh()->status)->toBe(CommissionSettlementStatus::DocumentReceived);
})->with(['commission-settlements.approve', 'purchase-invoices.create']);

it('AC-008: an APPROVED settlement cannot be approved again nor change supplier', function () {
    $settlement = approvableSettlement();
    Sanctum::actingAs(approvalUser());
    $this->postJson(approveUrl($settlement), approvalChoices())->assertOk();

    $this->postJson(approveUrl($settlement), approvalChoices())->assertStatus(409);
    $this->postJson("/api/commission-settlements/{$settlement->id}/supplier", ['registry_id' => null])->assertStatus(409);
    $this->getJson("/api/commission-settlements/{$settlement->id}/approval-draft")->assertStatus(409);
});

it('AC-010: paying every installment makes the settlement paid with a zero residual, an overdue one makes it overdue', function () {
    $settlement = approvableSettlement(['document_date' => '2026-01-10']);
    Sanctum::actingAs(approvalUser());
    $this->postJson(approveUrl($settlement), approvalChoices())->assertOk();
    $installments = PurchaseInvoice::query()->findOrFail($settlement->fresh()->purchase_invoice_id)->installments()->orderBy('sequence')->get();
    $payer = commissionSettlementUserWith(['view']);
    $payer->givePermissionTo(Permission::findOrCreate('purchase-invoices.pay', 'web'), Permission::findOrCreate('purchase-invoices.view', 'web'));
    Sanctum::actingAs($payer);

    $this->getJson("/api/commission-settlements/{$settlement->id}")->assertJsonPath('data.purchase_invoice.payment_status', 'seriously_overdue');

    foreach ($installments as $installment) {
        $this->putJson("/api/purchase-invoice-installments/{$installment->id}/payment", ['paid_amount' => (string) $installment->amount, 'paid_at' => '2026-10-10'])->assertOk();
    }

    $this->getJson("/api/commission-settlements/{$settlement->id}")
        ->assertJsonPath('data.purchase_invoice.payment_status', 'paid')
        ->assertJsonPath('data.purchase_invoice.paid_amount', '1020.00')
        ->assertJsonPath('data.purchase_invoice.residual_amount', '0.00');
});

<?php

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\EnasarcoParameter;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function documentUrl(CommissionSettlement $settlement): string
{
    return "/api/commission-settlements/{$settlement->id}/document";
}

it('AC-002: registers the document within the tolerance, with signed differences and no change of the amounts', function () {
    $settlement = amountedSettlement();
    $actor = commissionSettlementUserWith(['view', 'receiveDocument']);
    Sanctum::actingAs($actor);

    $this->postJson(documentUrl($settlement), settlementDocumentBody(['document_vat_amount' => '219.50', 'document_total_amount' => '1219.50']))
        ->assertOk()
        ->assertJsonPath('data.status', 'DOCUMENT_RECEIVED')
        ->assertJsonPath('data.document.number', 'FA-77')
        ->assertJsonPath('data.document.date', '2026-10-05')
        ->assertJsonPath('data.document.vat_amount', '219.50')
        ->assertJsonPath('data.document.total_amount', '1219.50')
        ->assertJsonPath('data.document.received_by.id', $actor->id)
        ->assertJsonPath('data.document.differences.vat_amount', '-0.50')
        ->assertJsonPath('data.document.differences.total_amount', '-0.50')
        ->assertJsonPath('data.document.differences.taxable_amount', '0.00')
        ->assertJsonPath('data.abilities.remove_document', true)
        ->assertJsonPath('data.abilities.receive_document', false)
        ->assertJsonPath('data.purchase_invoice', null);

    $fresh = $settlement->fresh();
    expect($fresh->document_received_at)->not->toBeNull()
        ->and($fresh->document_received_by)->toBe($actor->id)
        ->and($fresh->vat_amount)->toBe('220.00')
        ->and($fresh->net_payable)->toBe('1020.00');
});

it('AC-002: a total different from taxable plus VAT is a 422 on document_total_amount', function () {
    $settlement = amountedSettlement();
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument']));

    $this->postJson(documentUrl($settlement), settlementDocumentBody(['document_total_amount' => '1220.50']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_total_amount');

    expect($settlement->fresh()->status)->toBe(CommissionSettlementStatus::Issued);
});

it('AC-002: a difference over the tolerance is a 422 on the item with difference and tolerance in the message', function () {
    $settlement = amountedSettlement();
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument']));

    $response = $this->postJson(documentUrl($settlement), settlementDocumentBody(['document_withholding_amount' => '116.01']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_withholding_amount');

    expect($response->json('errors.document_withholding_amount.0'))->toContain('1.01')->toContain('1.00')
        ->and($settlement->fresh()->status)->toBe(CommissionSettlementStatus::Issued);
});

it('AC-002: the tolerance follows the config', function () {
    config(['commissions.document_tolerance' => '0.00']);
    $settlement = amountedSettlement();
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument']));

    $this->postJson(documentUrl($settlement), settlementDocumentBody(['document_enasarco_amount' => '85.01']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_enasarco_amount');
});

it('AC-002: the body is validated (required, max 30, no future date, amounts >= 0 with two decimals)', function (array $overrides, string $field) {
    $settlement = amountedSettlement();
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument']));

    $this->postJson(documentUrl($settlement), settlementDocumentBody($overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no number' => [['document_number' => ''], 'document_number'],
    'long number' => [['document_number' => str_repeat('a', 31)], 'document_number'],
    'future date' => [['document_date' => '2999-01-01'], 'document_date'],
    'negative amount' => [['document_vat_amount' => '-1.00'], 'document_vat_amount'],
    'three decimals' => [['document_taxable_amount' => '1000.001'], 'document_taxable_amount'],
]);

it('AC-002: registering from any state but ISSUED is a 409', function (string $state) {
    $settlement = amountedSettlement($state);
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument']));

    $this->postJson(documentUrl($settlement), settlementDocumentBody())->assertStatus(409);
})->with(['draft', 'document_received', 'approved', 'cancelled']);

it('AC-003: removes the document back to ISSUED with the document fields null', function () {
    $settlement = amountedSettlement('document_received');
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument']));

    $this->deleteJson(documentUrl($settlement))
        ->assertOk()
        ->assertJsonPath('data.status', 'ISSUED')
        ->assertJsonPath('data.document', null)
        ->assertJsonPath('data.abilities.receive_document', true);

    $fresh = $settlement->fresh();
    expect($fresh->document_number)->toBeNull()
        ->and($fresh->document_date)->toBeNull()
        ->and($fresh->document_total_amount)->toBeNull()
        ->and($fresh->document_received_by)->toBeNull()
        ->and($fresh->document_received_at)->toBeNull();
});

it('AC-003: removing from any state but DOCUMENT_RECEIVED is a 409', function (string $state) {
    $settlement = amountedSettlement($state);
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument']));

    $this->deleteJson(documentUrl($settlement))->assertStatus(409);
})->with(['draft', 'issued', 'approved', 'cancelled']);

it('AC-003: cancelling from DOCUMENT_RECEIVED is a 409', function () {
    $settlement = amountedSettlement('document_received');
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'cancel']));

    $this->postJson("/api/commission-settlements/{$settlement->id}/cancel", ['reason' => 'x'])->assertStatus(409);

    expect($settlement->fresh()->status)->toBe(CommissionSettlementStatus::DocumentReceived);
});

it('AC-008: an APPROVED settlement is immutable (recalculate, issue, cancel, notes, delete, document)', function () {
    $settlement = amountedSettlement('approved');
    Sanctum::actingAs(settlementAdmin());
    $url = "/api/commission-settlements/{$settlement->id}";

    $this->postJson("{$url}/recalculate")->assertStatus(409);
    $this->postJson("{$url}/issue")->assertStatus(409);
    $this->postJson("{$url}/cancel", ['reason' => 'x'])->assertStatus(409);
    $this->patchJson($url, ['notes' => 'x'])->assertStatus(409);
    $this->deleteJson($url)->assertStatus(409);
    $this->postJson("{$url}/document", settlementDocumentBody())->assertStatus(409);
    $this->deleteJson("{$url}/document")->assertStatus(409);

    expect($settlement->fresh()->status)->toBe(CommissionSettlementStatus::Approved);
});

it('AC-011: DOCUMENT_RECEIVED and APPROVED settlements count in the ENASARCO ceiling like the ISSUED ones', function (string $state) {
    $company = Company::factory()->create();
    EnasarcoParameter::factory()->create(['year' => 2026, 'single_firm_ceiling' => '1500.00', 'multi_firm_ceiling' => '1500.00']);
    $agent = settlementAgent(['enasarco_enabled' => true, 'enasarco_mandate_type' => 'mono']);
    settlementRow($agent, $company, $state, ['year' => 2026, 'number' => 1, 'period_to' => '2026-08-31', 'enasarco_enabled' => true, 'enasarco_base_amount' => '1000.00']);
    settlementMovement($agent, $company, '1000.00');

    $draft = draftSettlementFor($agent, $company);

    expect($draft->enasarco_base_amount)->toBe('500.00');
})->with(['issued', 'document_received', 'approved']);

it('AC-011: an ENASARCO year used by a DOCUMENT_RECEIVED or APPROVED settlement cannot be deleted', function (string $state) {
    $company = Company::factory()->create();
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);
    settlementRow(settlementAgent(), $company, $state, ['year' => 2026, 'number' => 1, 'period_to' => '2026-08-31', 'enasarco_enabled' => true]);
    Sanctum::actingAs(enasarcoParameterUserWith(['delete']));

    $this->deleteJson("/api/enasarco-parameters/{$parameter->id}")->assertStatus(409);
})->with(['document_received', 'approved']);

it('AC-011: the movements of a DOCUMENT_RECEIVED or APPROVED settlement show status issued', function (string $state) {
    $settlement = amountedSettlement($state);
    $movement = CommissionAccrual::factory()->create(['commission_settlement_id' => $settlement->id, 'recipient_id' => $settlement->referent_id, 'company_id' => $settlement->company_id]);
    Sanctum::actingAs(User::factory()->create()->givePermissionTo(Permission::findOrCreate('commission-accruals.viewAny')));

    $rows = collect(settlementTableRows('commission-accruals')['items'])->keyBy('id');

    expect($rows[$movement->id]['status'])->toBe('issued');
})->with(['document_received', 'approved']);

it('AC-016: the document endpoints answer 403 without receiveDocument', function () {
    $settlement = amountedSettlement('document_received');
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'approve']));

    $this->postJson(documentUrl($settlement), settlementDocumentBody())->assertForbidden();
    $this->deleteJson(documentUrl($settlement))->assertForbidden();
});

it('AC-016: registering is a 403 without receiveDocument even with an invalid body', function () {
    $settlement = amountedSettlement();
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'approve']));

    $this->postJson(documentUrl($settlement), [])->assertForbidden();
});

it('AC-016: the abilities of the detail are the permission AND the state', function () {
    $issued = amountedSettlement();
    $received = amountedSettlement('document_received');
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'receiveDocument', 'approve']));

    $this->getJson("/api/commission-settlements/{$issued->id}")
        ->assertJsonPath('data.abilities.receive_document', true)
        ->assertJsonPath('data.abilities.remove_document', false)
        ->assertJsonPath('data.abilities.approve', false);
    $this->getJson("/api/commission-settlements/{$received->id}")
        ->assertJsonPath('data.abilities.receive_document', false)
        ->assertJsonPath('data.abilities.remove_document', true)
        ->assertJsonPath('data.abilities.approve', true);

    Sanctum::actingAs(commissionSettlementUserWith(['view']));
    $this->getJson("/api/commission-settlements/{$received->id}")
        ->assertJsonPath('data.abilities.remove_document', false)
        ->assertJsonPath('data.abilities.approve', false);
});

it('AC-016: the document amounts follow the visibility of the settlement amounts', function () {
    $settlement = amountedSettlement('document_received');
    $user = commissionSettlementUserWith(['view']);
    hideSettlementFields($user, ['vat_amount']);
    Sanctum::actingAs($user);

    $document = $this->getJson("/api/commission-settlements/{$settlement->id}")->json('data.document');

    expect($document)->not->toHaveKeys(['vat_amount', 'total_amount'])
        ->and($document['differences'])->not->toHaveKeys(['vat_amount', 'total_amount'])
        ->and($document)->toHaveKeys(['taxable_amount', 'withholding_amount', 'enasarco_amount']);
});

it('shows the linked purchase invoice with paid, residual and payment status, and the agent supplier', function () {
    $invoice = PurchaseInvoice::factory()->create(['total_amount' => '1220.00', 'withholding_amount' => '115.00', 'enasarco_amount' => '85.00']);
    PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => '510.00', 'paid_amount' => '510.00', 'due_date' => now()->subDays(40)->toDateString()]);
    PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $invoice->id, 'sequence' => 2, 'amount' => '510.00', 'due_date' => now()->subDays(5)->toDateString()]);
    $settlement = amountedSettlement('approved', ['purchase_invoice_id' => $invoice->id]);
    $settlement->referent->update(['supplier_registry_id' => $invoice->supplier_id]);
    Sanctum::actingAs(commissionSettlementUserWith(['view']));

    $this->getJson("/api/commission-settlements/{$settlement->id}")
        ->assertOk()
        ->assertJsonPath('data.purchase_invoice.id', $invoice->id)
        ->assertJsonPath('data.purchase_invoice.payable_amount', '1020.00')
        ->assertJsonPath('data.purchase_invoice.paid_amount', '510.00')
        ->assertJsonPath('data.purchase_invoice.residual_amount', '510.00')
        ->assertJsonPath('data.purchase_invoice.payment_status', 'overdue')
        ->assertJsonPath('data.supplier.id', $invoice->supplier_id);

    $hidden = commissionSettlementUserWith(['view']);
    hideSettlementFields($hidden, ['net_payable']);
    Sanctum::actingAs($hidden);
    expect($this->getJson("/api/commission-settlements/{$settlement->id}")->json('data.purchase_invoice'))
        ->not->toHaveKeys(['payable_amount', 'paid_amount', 'residual_amount'])->toHaveKey('payment_status');
});

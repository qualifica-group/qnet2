<?php

use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view'], ['purchase-invoices.pay']));
});

function clearPayment(int $installmentId): TestResponse
{
    return test()->deleteJson("/api/purchase-invoice-installments/{$installmentId}/payment");
}

it('AC-007: a full payment closes the installment with the date and the output account', function () {
    $document = purchaseDocumentWithPlan();
    $account = FinancialAccount::factory()->create(['company_id' => $document->company_id, 'name' => 'Banca Uno']);
    $first = $document->installments()->first();

    $response = payInstallment($first->id, '400', ['financial_account_id' => $account->id])->assertOk()->assertJsonPath('message', 'Payment recorded.');

    expect($response->json('data'))->toMatchArray(['status' => 'paid', 'paid_amount' => '400.00', 'paid_at' => '2026-04-20', 'residual_amount' => '0.00'])
        ->and($response->json('data.financial_account'))->toBe(['id' => $account->id, 'name' => 'Banca Uno'])
        ->and(purchasePlan($document))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and($first->fresh()->redistribution_snapshot)->toBeNull();
});

it('AC-007: a partial payment with spread closes the installment and adds the residual to the later ones', function () {
    $document = purchaseDocumentWithPlan();

    $response = payInstallment($document->installments()->first()->id, '200', ['residual_mode' => 'spread'])->assertOk();

    expect(purchasePlan($document))->toBe(['1:200.00', '2:500.00', '3:500.00'])
        ->and($response->json('data.status'))->toBe('paid')
        ->and(array_column($response->json('data.purchase_invoice.installments'), 'status'))->toBe(['paid', 'unpaid', 'unpaid']);
});

it('AC-007: a partial payment with new_installment inserts the residual after it and renumbers the others', function () {
    $document = purchaseDocumentWithPlan();

    payInstallment($document->installments()->first()->id, '200', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-05-01'])->assertOk();

    expect(purchasePlan($document))->toBe(['1:200.00', '2:200.00', '3:400.00', '4:400.00'])
        ->and($document->installments()->get()->map(fn (PurchaseInvoiceInstallment $row): string => $row->due_date->toDateString())->all())
        ->toBe(['2026-04-14', '2026-05-01', '2026-05-14', '2026-06-13']);
});

it('AC-007: spread on the last installment is a 422, new_installment creates the installment', function () {
    $document = purchaseDocumentWithPlan();
    $lastId = $document->installments()->get()->last()->id;

    payInstallment($lastId, '100', ['residual_mode' => 'spread'])
        ->assertUnprocessable()->assertJsonValidationErrors(['residual_mode' => 'There are no later open installments to spread the residual on.']);
    expect(PurchaseInvoiceInstallment::query()->whereKey($lastId)->value('paid_amount'))->toBeNull();

    payInstallment($lastId, '100', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-07-01'])->assertOk();

    expect(purchasePlan($document))->toBe(['1:400.00', '2:400.00', '3:100.00', '4:300.00']);
});

it('AC-007: a partial payment needs residual_mode, and new_installment a date not before the document date', function () {
    $id = purchaseDocumentWithPlan()->installments()->first()->id;

    payInstallment($id, '200')->assertUnprocessable()->assertJsonValidationErrors('residual_mode');
    payInstallment($id, '200', ['residual_mode' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('residual_mode');
    payInstallment($id, '200', ['residual_mode' => 'new_installment'])->assertUnprocessable()->assertJsonValidationErrors('residual_due_date');
    payInstallment($id, '200', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-03-14'])->assertUnprocessable()->assertJsonValidationErrors('residual_due_date');
    payInstallment($id, '400.01')->assertUnprocessable()->assertJsonValidationErrors('paid_amount');

    expect(PurchaseInvoiceInstallment::query()->whereKey($id)->value('paid_amount'))->toBeNull();
});

it('AC-008: paying an already paid installment is a 409 and the payment does not change', function () {
    $id = purchaseDocumentWithPlan()->installments()->first()->id;
    payInstallment($id, '400')->assertOk();

    payInstallment($id, '100', ['paid_at' => '2026-05-05'])
        ->assertStatus(409)->assertJsonPath('message', 'This installment is already paid: clear the payment first.');

    $row = PurchaseInvoiceInstallment::query()->findOrFail($id);
    expect($row->paid_amount)->toBe('400.00')->and($row->paid_at->toDateString())->toBe('2026-04-20');
});

it('AC-008: clearing a spread payment restores the previous plan and resets the account', function () {
    $document = purchaseDocumentWithPlan();
    $account = FinancialAccount::factory()->create(['company_id' => null]);
    $id = $document->installments()->first()->id;
    payInstallment($id, '200', ['residual_mode' => 'spread', 'financial_account_id' => $account->id])->assertOk();

    $response = clearPayment($id)->assertOk()->assertJsonPath('message', 'Payment cleared.');

    expect(purchasePlan($document))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and($response->json('data'))->toMatchArray(['status' => 'unpaid', 'paid_amount' => null, 'paid_at' => null, 'financial_account' => null])
        ->and(PurchaseInvoiceInstallment::query()->whereKey($id)->value('redistribution_snapshot'))->toBeNull();
});

it('AC-008: clearing a new_installment payment deletes the residual installment and restores the sequences', function () {
    $document = purchaseDocumentWithPlan();
    $ids = $document->installments()->pluck('id')->all();
    payInstallment($ids[0], '200', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-05-01'])->assertOk();
    expect(PurchaseInvoiceInstallment::query()->count())->toBe(4);

    clearPayment($ids[0])->assertOk();

    expect(purchasePlan($document))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and($document->installments()->pluck('id')->all())->toBe($ids);
});

it('AC-008: a touched installment paid afterwards blocks the clearing (409) until it is cleared first', function () {
    $document = purchaseDocumentWithPlan();
    $ids = $document->installments()->pluck('id')->all();
    payInstallment($ids[0], '200', ['residual_mode' => 'spread'])->assertOk();
    payInstallment($ids[1], '500')->assertOk();

    clearPayment($ids[0])->assertStatus(409)->assertJsonPath('message', 'Clear the later payments first.');
    expect(purchasePlan($document))->toBe(['1:200.00', '2:500.00', '3:500.00']);

    clearPayment($ids[1])->assertOk();
    clearPayment($ids[0])->assertOk();

    expect(purchasePlan($document))->toBe(['1:400.00', '2:400.00', '3:400.00']);
});

it('AC-009: an account of another company is a 422, a company-less account is accepted', function () {
    $document = purchaseDocumentWithPlan();
    $id = $document->installments()->first()->id;
    $foreign = FinancialAccount::factory()->create(['company_id' => Company::factory()->create()->id]);
    $shared = FinancialAccount::factory()->create(['company_id' => null]);

    payInstallment($id, '400', ['financial_account_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['financial_account_id' => 'The account must belong to the document company.']);
    expect(PurchaseInvoiceInstallment::query()->whereKey($id)->value('paid_amount'))->toBeNull();

    payInstallment($id, '400', ['financial_account_id' => $shared->id])->assertOk();
});

it('AC-009: without purchase-invoices.pay the payment is a 403 before any validation, and nothing changes', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view', 'update']));
    $id = purchaseDocumentWithPlan()->installments()->first()->id;

    payInstallment($id, '400')->assertForbidden();
    test()->putJson("/api/purchase-invoice-installments/{$id}/payment", [])->assertForbidden();
    clearPayment($id)->assertForbidden();

    expect(PurchaseInvoiceInstallment::query()->whereKey($id)->value('paid_amount'))->toBeNull();
    $this->putJson('/api/purchase-invoice-installments/999999/payment', [])->assertNotFound();
});

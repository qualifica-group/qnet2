<?php

use App\Models\FinancialAccount;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0361 - write-off of the residual when a purchase invoice installment is paid.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->document = purchaseDocumentWithPlan();
    $this->first = $this->document->installments()->orderBy('sequence')->first();
    Sanctum::actingAs(purchaseInstallmentUserWith(['view'], ['purchase-invoices.pay']));
});

function clearPaymentWriteOff(PurchaseInvoiceInstallment $installment): TestResponse
{
    return test()->deleteJson("/api/purchase-invoice-installments/{$installment->id}/payment");
}

it('AC-002 closes the installment with a write-off and touches nothing else', function () {
    $response = payInstallment($this->first->id, '399.80', [
        'residual_mode' => 'write_off', 'write_off_reason' => 'rounding', 'write_off_note' => 'bank fee',
    ])->assertOk();

    $row = $this->first->fresh();
    expect($row->amount)->toBe('400.00')
        ->and($row->paid_amount)->toBe('399.80')
        ->and($row->write_off_amount)->toBe('0.20')
        ->and($row->residualAmount())->toBe('0.00')
        ->and($row->redistribution_snapshot)->toBeNull()
        ->and(purchasePlan($this->document))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and($response->json('data.status'))->toBe('paid')
        ->and($response->json('data.residual_amount'))->toBe('0.00')
        ->and($response->json('data.write_off'))->toBe(['amount' => '0.20', 'reason' => 'rounding', 'note' => 'bank fee'])
        ->and($response->json('data.purchase_invoice.installments.0.write_off'))->toBe(['amount' => '0.20', 'reason' => 'rounding', 'note' => 'bank fee'])
        ->and($response->json('data.purchase_invoice.installments.1.write_off'))->toBeNull();
});

it('AC-003 a zero payment with write-off closes a never paid installment and drops the account', function () {
    $account = FinancialAccount::factory()->create(['company_id' => $this->document->company_id]);

    payInstallment($this->first->id, '0', ['residual_mode' => 'write_off', 'write_off_reason' => 'bad_debt', 'financial_account_id' => $account->id])->assertOk();

    $row = $this->first->fresh();
    expect($row->write_off_amount)->toBe('400.00')
        ->and($row->paid_amount)->toBe('0.00')
        ->and($row->financial_account_id)->toBeNull()
        ->and($row->isPaid())->toBeTrue()
        ->and($row->residualAmount())->toBe('0.00');
});

it('AC-003 a zero payment without write-off is a 422', function () {
    payInstallment($this->first->id, '0')->assertUnprocessable()->assertJsonValidationErrors(['paid_amount']);

    expect($this->first->fresh()->paid_amount)->toBeNull();
});

it('AC-004 rejects malformed write-off requests and changes nothing', function (array $payload, string $field) {
    payInstallment($this->first->id, $payload['paid_amount'], $payload)->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect($this->first->fresh()->paid_amount)->toBeNull()->and($this->first->fresh()->write_off_amount)->toBeNull();
})->with([
    'no reason' => [['paid_amount' => '399.80', 'residual_mode' => 'write_off'], 'write_off_reason'],
    'invalid reason' => [['paid_amount' => '399.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'gift'], 'write_off_reason'],
    'reason without write-off' => [['paid_amount' => '399.80', 'residual_mode' => 'spread', 'write_off_reason' => 'rounding'], 'write_off_reason'],
    'note without write-off' => [['paid_amount' => '399.80', 'residual_mode' => 'spread', 'write_off_note' => 'x'], 'write_off_note'],
    'note too long' => [['paid_amount' => '399.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding', 'write_off_note' => str_repeat('a', 256)], 'write_off_note'],
    'full payment' => [['paid_amount' => '400.00', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding'], 'residual_mode'],
]);

it('AC-005 clearing the payment removes the write-off', function () {
    payInstallment($this->first->id, '399.80', ['residual_mode' => 'write_off', 'write_off_reason' => 'rounding'])->assertOk();

    $response = clearPaymentWriteOff($this->first)->assertOk();

    $row = $this->first->fresh();
    expect($row->paid_amount)->toBeNull()
        ->and($row->write_off_amount)->toBeNull()
        ->and($row->write_off_reason)->toBeNull()
        ->and($row->residualAmount())->toBe('400.00')
        ->and($response->json('data.write_off'))->toBeNull();
});

it('AC-006 a document with paid and written-off installments has every installment paid', function () {
    $rows = $this->document->installments()->orderBy('sequence')->get();
    payInstallment($rows[0]->id, '400')->assertOk();
    payInstallment($rows[1]->id, '0', ['residual_mode' => 'write_off', 'write_off_reason' => 'bad_debt'])->assertOk();
    $last = payInstallment($rows[2]->id, '399', ['residual_mode' => 'write_off', 'write_off_reason' => 'rounding'])->assertOk();

    expect(array_column($last->json('data.purchase_invoice.installments'), 'status'))->toBe(['paid', 'paid', 'paid']);
});

it('forbids a user without the pay permission', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view']));

    payInstallment($this->first->id, '399.80', ['residual_mode' => 'write_off', 'write_off_reason' => 'rounding'])->assertForbidden();
});

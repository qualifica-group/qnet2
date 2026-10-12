<?php

use App\Models\PaymentMethod;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function openPurchaseInstallment(array $attributes = []): PurchaseInvoiceInstallment
{
    return purchaseInstallmentOf(
        purchaseDocument(Registry::factory()->supplier()->create(['name' => 'Acme']), ['document_date' => '2026-03-15']),
        $attributes + ['due_date' => '2026-04-14', 'amount' => '100.00'],
    );
}

it('AC-006: shows an installment with the document, the field permissions and the abilities', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view', 'update']));
    PaymentMethod::factory()->create(['payment_method_code' => 'RB30']);
    $installment = openPurchaseInstallment(['payment_method_code' => 'RB30']);
    $installment->purchaseInvoice->purchaseOrders()->attach(PurchaseOrder::factory()->create(['number' => 3, 'year' => 2026])->id);

    $data = $this->getJson("/api/purchase-invoice-installments/{$installment->id}")->assertOk()->json('data');

    expect($data)->toMatchArray([
        'id' => $installment->id, 'sequence' => 1, 'due_date' => '2026-04-14', 'amount' => '100.00', 'payment_method_code' => 'RB30',
        'paid_amount' => null, 'paid_at' => null, 'status' => 'unpaid', 'residual_amount' => '100.00', 'is_overdue' => true,
        'financial_account' => null, 'abilities' => ['update' => true, 'pay' => false],
    ])
        ->and($data['purchase_invoice'])->toMatchArray(['document_date' => '2026-03-15', 'document_type' => 'invoice'])
        ->and($data['purchase_invoice']['supplier']['name'])->toBe('Acme')
        ->and($data['purchase_invoice']['purchase_orders'])->toHaveCount(1)
        ->and($data['purchase_invoice']['purchase_orders'][0]['code'])->toBe('3/2026')
        ->and($data['purchase_invoice']['installments'])->toHaveCount(1)
        ->and($data['field_permissions']['due_date'])->toBe(['visible' => true, 'editable' => true, 'required' => true])
        ->and($data['field_permissions']['amount'])->toBe(['visible' => true, 'editable' => false, 'required' => false])
        ->and(array_keys($data['field_permissions']))->toBe(['due_date', 'payment_method_code', 'amount', 'paid_amount', 'paid_at', 'residual_amount', 'financial_account']);

    $this->getJson('/api/purchase-invoice-installments/999999')->assertNotFound();
});

it('AC-006: updates the due date and the payment method code of an open installment and writes the activity log', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view', 'update']));
    PaymentMethod::factory()->create(['payment_method_code' => 'BB60']);
    $installment = openPurchaseInstallment();

    $response = $this->patchJson("/api/purchase-invoice-installments/{$installment->id}", ['due_date' => '2026-06-30', 'payment_method_code' => 'BB60'])
        ->assertOk()->assertJsonPath('message', 'Installment updated.');

    expect($response->json('data'))->toMatchArray(['due_date' => '2026-06-30', 'payment_method_code' => 'BB60'])
        ->and($installment->fresh()->due_date->toDateString())->toBe('2026-06-30');

    $log = Activity::query()->where('subject_type', 'purchase_invoice_installment')->where('subject_id', $installment->id)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($log->properties['attributes']['due_date'])->toStartWith('2026-06-30')
        ->and($log->properties['attributes']['payment_method_code'])->toBe('BB60');

    $this->patchJson("/api/purchase-invoice-installments/{$installment->id}", ['payment_method_code' => null])->assertOk()->assertJsonPath('data.payment_method_code', null);
});

it('AC-006: a paid installment is a 409', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view', 'update']));
    $installment = openPurchaseInstallment(['paid_amount' => '30.00', 'paid_at' => '2026-04-20']);

    $this->patchJson("/api/purchase-invoice-installments/{$installment->id}", ['due_date' => '2026-06-30'])
        ->assertStatus(409)->assertJsonPath('message', 'Paid installments cannot be edited.');

    expect($installment->fresh()->due_date->toDateString())->toBe('2026-04-14');
});

it('AC-006: validation errors are 422', function (array $payload) {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view', 'update']));
    $installment = openPurchaseInstallment();

    $this->patchJson("/api/purchase-invoice-installments/{$installment->id}", $payload)->assertUnprocessable();
})->with([
    'due date before the document date' => [['due_date' => '2026-03-14']],
    'malformed due date' => [['due_date' => '14/04/2026']],
    'unknown payment method code' => [['payment_method_code' => 'NOPE']],
    'code too long' => [['payment_method_code' => str_repeat('x', 33)]],
    'nothing to update' => [[]],
    'amount is not accepted' => [['amount' => '5.00']],
]);

it('AC-006: without purchase-invoice-installments.update the PATCH is a 403 and nothing changes', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['view']));
    $installment = openPurchaseInstallment();

    $this->patchJson("/api/purchase-invoice-installments/{$installment->id}", ['due_date' => '2026-06-30'])->assertForbidden();

    expect($installment->fresh()->due_date->toDateString())->toBe('2026-04-14');
});

it('AC-006: a field locked by field permission is a 403', function () {
    $user = purchaseInstallmentUserWith(['view', 'update']);
    $role = Role::create(['name' => 'no-code-edit']);
    $role->fieldPermissions()->create(['resource' => 'purchase-invoice-installments', 'field' => 'payment_method_code', 'visible' => true, 'editable' => false, 'required' => false]);
    $user->assignRole($role);
    Sanctum::actingAs($user);
    PaymentMethod::factory()->create(['payment_method_code' => 'BB60']);
    $installment = openPurchaseInstallment();

    $this->patchJson("/api/purchase-invoice-installments/{$installment->id}", ['payment_method_code' => 'BB60'])->assertForbidden();
    $this->patchJson("/api/purchase-invoice-installments/{$installment->id}", ['due_date' => '2026-06-30'])->assertOk()
        ->assertJsonPath('data.field_permissions.payment_method_code.editable', false);
});

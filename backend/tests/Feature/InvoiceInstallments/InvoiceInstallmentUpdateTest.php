<?php

use App\Models\InvoiceInstallment;
use App\Models\PaymentMethod;
use App\Models\Registry;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function openInstallment(array $attributes = []): InvoiceInstallment
{
    return installmentOf(installmentInvoice(Registry::factory()->create(['name' => 'Acme']), null, null, ['document_date' => '2026-03-15']), $attributes + ['due_date' => '2026-04-14', 'amount' => '100.00']);
}

it('shows an installment with the invoice, the field permissions and the abilities', function () {
    Sanctum::actingAs(installmentUserWith(['view', 'update'], ['invoices.view']));
    PaymentMethod::factory()->create(['payment_method_code' => 'RB30']);
    $installment = openInstallment(['payment_method_code' => 'RB30']);

    $response = $this->getJson("/api/invoice-installments/{$installment->id}")->assertOk();

    expect($response->json('data'))->toMatchArray([
        'id' => $installment->id, 'sequence' => $installment->sequence, 'due_date' => '2026-04-14', 'amount' => '100.00',
        'payment_method_code' => 'RB30', 'collected_amount' => null, 'collected_at' => null, 'status' => 'unpaid',
        'residual_amount' => '100.00', 'is_overdue' => true,
        'abilities' => ['update' => true, 'collect' => false, 'view_invoice' => true],
    ])
        ->and($response->json('data.invoice'))->toMatchArray(['number_label' => $installment->invoice->number.'/'.$installment->invoice->year, 'document_date' => '2026-03-15'])
        ->and($response->json('data.invoice.customer.name'))->toBe('Acme')
        ->and($response->json('data.invoice.work_order.code'))->toBe($installment->invoice->workOrder->code)
        ->and($response->json('data.invoice.company_site'))->toBeNull()
        ->and($response->json('data.invoice.operational_site'))->toBeNull()
        ->and($response->json('data.field_permissions'))->toBe([
            'due_date' => ['visible' => true, 'editable' => true, 'required' => true],
            'payment_method_code' => ['visible' => true, 'editable' => true, 'required' => false],
            'amount' => ['visible' => true, 'editable' => false, 'required' => false],
            'collected_amount' => ['visible' => true, 'editable' => false, 'required' => false],
            'collected_at' => ['visible' => true, 'editable' => false, 'required' => false],
        ]);

    $this->getJson('/api/invoice-installments/999999')->assertNotFound();
});

it('AC-011: updates the due date and the payment method code of an open installment and writes the activity log', function () {
    Sanctum::actingAs(installmentUserWith(['view', 'update']));
    PaymentMethod::factory()->create(['payment_method_code' => 'BB60']);
    $installment = openInstallment();

    $response = $this->patchJson("/api/invoice-installments/{$installment->id}", ['due_date' => '2026-06-30', 'payment_method_code' => 'BB60'])
        ->assertOk()->assertJsonPath('message', 'Installment updated.');

    expect($response->json('data'))->toMatchArray(['due_date' => '2026-06-30', 'payment_method_code' => 'BB60'])
        ->and($installment->fresh()->only(['due_date', 'payment_method_code']))->toMatchArray(['payment_method_code' => 'BB60'])
        ->and($installment->fresh()->due_date->toDateString())->toBe('2026-06-30');

    $log = Activity::query()->where('subject_type', 'invoice_installment')->where('subject_id', $installment->id)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($log->properties['attributes']['due_date'])->toStartWith('2026-06-30')
        ->and($log->properties['attributes']['payment_method_code'])->toBe('BB60');

    $this->patchJson("/api/invoice-installments/{$installment->id}", ['payment_method_code' => null])->assertOk()->assertJsonPath('data.payment_method_code', null);
    $this->patchJson("/api/invoice-installments/{$installment->id}", ['due_date' => '2026-07-01'])->assertOk()->assertJsonPath('data.due_date', '2026-07-01');
});

it('AC-012: an installment with a collection is a 409', function () {
    Sanctum::actingAs(installmentUserWith(['view', 'update']));
    $installment = openInstallment(['collected_amount' => '30.00', 'collected_at' => '2026-04-20']);

    $this->patchJson("/api/invoice-installments/{$installment->id}", ['due_date' => '2026-06-30'])
        ->assertStatus(409)->assertJsonPath('message', 'Collected installments cannot be edited.');

    expect($installment->fresh()->due_date->toDateString())->toBe('2026-04-14');
});

it('AC-012: validation errors are 422', function (array $payload) {
    Sanctum::actingAs(installmentUserWith(['view', 'update']));
    $installment = openInstallment();

    $this->patchJson("/api/invoice-installments/{$installment->id}", $payload)->assertUnprocessable();
})->with([
    'due date before the document date' => [['due_date' => '2026-03-14']],
    'malformed due date' => [['due_date' => '14/04/2026']],
    'unknown payment method code' => [['payment_method_code' => 'NOPE']],
    'code too long' => [['payment_method_code' => str_repeat('x', 33)]],
    'nothing to update' => [[]],
    'amount is not accepted' => [['amount' => '5.00']],
]);

it('AC-012: without invoice-installments.update the PATCH is a 403 and nothing changes', function () {
    Sanctum::actingAs(installmentUserWith(['view']));
    $installment = openInstallment();

    $this->patchJson("/api/invoice-installments/{$installment->id}", ['due_date' => '2026-06-30'])->assertForbidden();

    expect($installment->fresh()->due_date->toDateString())->toBe('2026-04-14');
});

it('AC-012: a field locked by field permission is a 403', function () {
    $user = installmentUserWith(['view', 'update']);
    $role = Role::create(['name' => 'no-code-edit']);
    $role->fieldPermissions()->create(['resource' => 'invoice-installments', 'field' => 'payment_method_code', 'visible' => true, 'editable' => false, 'required' => false]);
    $user->assignRole($role);
    Sanctum::actingAs($user);
    PaymentMethod::factory()->create(['payment_method_code' => 'BB60']);
    $installment = openInstallment();

    $this->patchJson("/api/invoice-installments/{$installment->id}", ['payment_method_code' => 'BB60'])->assertForbidden();
    $this->patchJson("/api/invoice-installments/{$installment->id}", ['due_date' => '2026-06-30'])->assertOk()
        ->assertJsonPath('data.field_permissions.payment_method_code.editable', false);
});

it('AC-013: a later rebalancing of the invoice keeps the edited due date of the open installments', function () {
    $user = invoiceUserWith(['viewAny', 'view', 'create', 'update', 'collect']);
    $user->givePermissionTo(Permission::findOrCreate('invoice-installments.update', 'web'), Permission::findOrCreate('invoice-installments.view', 'web'));
    Sanctum::actingAs($user);
    [$invoice, $payload] = invoiceWithPlan('1200.00');
    collectInstallment($invoice['installments'][0]['id'], '400')->assertOk();
    $second = $invoice['installments'][1]['id'];

    $this->patchJson("/api/invoice-installments/{$second}", ['due_date' => '2026-12-31'])->assertOk();
    $response = $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1000.00'))->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:300.00', '3:300.00'])
        ->and($response->json('data.installments.1.due_date'))->toStartWith('2026-12-31')
        ->and($response->json('data.installments.2.due_date'))->toBe($invoice['installments'][2]['due_date']);
});

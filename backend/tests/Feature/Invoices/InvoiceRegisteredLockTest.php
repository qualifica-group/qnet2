<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\ProformaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const REGISTERED_BASE_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete', 'collect'];

/**
 * A registered invoice (type=invoice) issued through the API; the actor is left to the caller.
 *
 * @return array{0: ProformaRequest, 1: array<string, mixed>, 2: array<string, mixed>} [request, resource data, write payload]
 */
function registeredInvoice(): array
{
    $request = invoiceRequest();
    $payload = invoicePayload($request);
    $data = test()->postJson("/api/proforma-requests/{$request->id}/invoice", $payload)->assertCreated()->json('data');
    test()->patchJson("/api/invoices/{$data['id']}/details", ['external_number' => 'FT-9', 'external_date' => '2026-04-01'])
        ->assertOk()->assertJsonPath('data.type', 'invoice');

    return [$request, $data, $payload];
}

it('AC-012: PUT on a registered invoice answers 409 and changes nothing', function () {
    Sanctum::actingAs(invoiceUserWith(REGISTERED_BASE_ABILITIES));
    [, $invoice, $payload] = registeredInvoice();

    $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '500.00'))
        ->assertStatus(409)->assertJsonPath('message', __('invoices.registered_locked'));

    $this->getJson("/api/invoices/{$invoice['id']}")->assertOk()
        ->assertJsonPath('data.total_amount', $invoice['total_amount'])
        ->assertJsonCount(count($invoice['installments']), 'data.installments');
});

it('AC-012: the lock is checked before the business validation', function () {
    Sanctum::actingAs(invoiceUserWith(REGISTERED_BASE_ABILITIES));
    [, $invoice, $payload] = registeredInvoice();

    $this->putJson("/api/invoices/{$invoice['id']}", [...$payload, 'company_id' => Company::factory()->create()->id])
        ->assertStatus(409);
});

it('AC-013: DELETE on a registered invoice answers 409 and the request stays issued', function () {
    Sanctum::actingAs(invoiceUserWith(REGISTERED_BASE_ABILITIES));
    [$request, $invoice] = registeredInvoice();

    $this->deleteJson("/api/invoices/{$invoice['id']}")->assertStatus(409)->assertJsonPath('message', __('invoices.registered_locked'));

    expect(Invoice::query()->count())->toBe(1)->and($request->refresh()->status->value)->toBe('issued');
});

it('AC-014: without downgrade, changing or clearing the external number is 403 while the free details stay editable', function () {
    $admin = invoiceUserWith([...REGISTERED_BASE_ABILITIES, 'downgrade']);
    $plain = invoiceUserWith(REGISTERED_BASE_ABILITIES);
    Sanctum::actingAs($admin);
    [, $invoice] = registeredInvoice();
    $url = "/api/invoices/{$invoice['id']}/details";

    Sanctum::actingAs($plain);

    $this->patchJson($url, ['external_number' => null])->assertForbidden()->assertJsonPath('message', __('invoices.downgrade_forbidden'));
    $this->patchJson($url, ['external_number' => 'FT-10', 'external_date' => '2026-04-01'])->assertForbidden();
    $this->patchJson($url, ['external_number' => 'FT-9', 'external_date' => '2026-04-02'])->assertForbidden();
    expect(Invoice::query()->findOrFail($invoice['id'])->type->value)->toBe('invoice');

    $this->patchJson($url, ['external_number' => 'FT-9', 'external_date' => '2026-04-01', 'invoice_tag_id' => referenceInvoiceTag('Consuntivo')->id, 'deviation' => '1.5', 'internal_note' => 'ok'])
        ->assertOk()->assertJsonPath('data.type', 'invoice')->assertJsonPath('data.tag.name', 'Consuntivo');
    $this->patchJson($url, ['invoice_tag_id' => referenceInvoiceTag('Preventivo')->id])->assertOk()->assertJsonPath('data.tag.name', 'Preventivo');
});

it('AC-015: with downgrade, clearing the external number reverts to proforma and the activity log records it', function () {
    Sanctum::actingAs(invoiceUserWith([...REGISTERED_BASE_ABILITIES, 'downgrade']));
    [, $invoice] = registeredInvoice();

    $this->patchJson("/api/invoices/{$invoice['id']}/details", ['external_number' => null])
        ->assertOk()->assertJsonPath('data.type', 'proforma')->assertJsonPath('data.external_number', null);

    $changes = Activity::query()->where('subject_id', $invoice['id'])->where('event', 'updated')->latest('id')->firstOrFail()->properties;

    expect($changes['attributes'])->toHaveKeys(['external_number', 'type'])
        ->and($changes['old']['external_number'])->toBe('FT-9')
        ->and($changes['old']['type'])->toBe('invoice');
});

it('AC-016: the table offers no update/delete on a registered invoice but keeps details', function () {
    Sanctum::actingAs(invoiceUserWith([...REGISTERED_BASE_ABILITIES, 'downgrade']));
    $registered = Invoice::factory()->create(['type' => 'invoice', 'external_number' => 'FT-1', 'external_date' => '2026-01-01']);
    $proforma = Invoice::factory()->create();

    $rows = collect($this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))->keyBy('id');

    // Spec 0261 (REQUIREMENT CHANGED): edit and details live on the record; only delete depends on registration.
    expect($rows[$registered->id]['actions'])->toContain('view')->not->toContain('delete')
        ->and($rows[$proforma->id]['actions'])->toContain('view', 'delete');
});

it('AC-016: the detail exposes the downgrade action and the read-only header fields of a registered invoice', function () {
    $withDowngrade = invoiceUserWith([...REGISTERED_BASE_ABILITIES, 'downgrade']);
    $without = invoiceUserWith(REGISTERED_BASE_ABILITIES);
    $registered = Invoice::factory()->create(['type' => 'invoice', 'external_number' => 'FT-1', 'external_date' => '2026-01-01']);
    $proforma = Invoice::factory()->create();

    Sanctum::actingAs($withDowngrade);
    $response = $this->getJson("/api/invoices/{$registered->id}")->assertOk()
        ->assertJsonPath('permissions.actions.downgrade', true)
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.fields.external_number.editable', true)
        ->assertJsonPath('permissions.fields.external_date.editable', true)
        ->assertJsonPath('permissions.fields.invoice_tag_id.editable', true)
        ->assertJsonPath('permissions.fields.internal_note.editable', true)
        ->assertJsonPath('permissions.fields.deviation.editable', true);

    foreach (['document_date', 'company_id', 'customer_registry_id', 'payment_method_id', 'financial_account_id', 'notes'] as $field) {
        $response->assertJsonPath("permissions.fields.{$field}.editable", false)->assertJsonPath("permissions.fields.{$field}.visible", true);
    }

    $this->getJson("/api/invoices/{$proforma->id}")->assertOk()
        ->assertJsonPath('permissions.actions.downgrade', false)
        ->assertJsonPath('permissions.actions.delete', true)
        ->assertJsonPath('permissions.fields.document_date.editable', true);

    Sanctum::actingAs($without);
    $this->getJson("/api/invoices/{$registered->id}")->assertOk()
        ->assertJsonPath('permissions.actions.downgrade', false)
        ->assertJsonPath('permissions.fields.external_number.editable', false)
        ->assertJsonPath('permissions.fields.external_date.editable', false)
        ->assertJsonPath('permissions.fields.invoice_tag_id.editable', true);
});

it('AC-017: permissions:sync creates invoices.downgrade and the super-admin receives it', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $this->artisan('roles:create-super-admin')->assertSuccessful();

    expect(Permission::query()->where('name', 'invoices.downgrade')->exists())->toBeTrue()
        ->and(Role::findByName('super-admin')->hasPermissionTo('invoices.downgrade'))->toBeTrue();
});

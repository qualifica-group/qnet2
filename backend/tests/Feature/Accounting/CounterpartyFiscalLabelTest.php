<?php

declare(strict_types=1);

use App\Models\CommissionAccrual;
use App\Models\Invoice;
use App\Models\PersonalData;
use App\Models\ProformaRequest;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User request 2026-11-12: the accounting lists and records show the customer
// or supplier as "name - VAT number", the tax code when there is no VAT number.

uses(RefreshDatabase::class);

/**
 * @param  array{vat_number?: string|null, tax_code?: string|null}  $fiscal
 */
function fiscalRegistry(array $fiscal, bool $supplier = false): Registry
{
    $factory = $supplier ? Registry::factory()->supplier() : Registry::factory();

    return $factory
        ->withPersonalData(static fn (Factory $card): Factory => $card->state($fiscal + ['vat_number' => null, 'tax_code' => null]))
        ->create()
        ->refresh();
}

/**
 * @return array<string, mixed>
 */
function fiscalGridRow(string $resource): array
{
    return test()->postJson("/api/tables/{$resource}/rows", ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');
}

it('shows the customer VAT number next to the name in the active invoices and their installments', function () {
    Sanctum::actingAs(installmentUserWith(['viewAny'], ['invoices.viewAny']));
    $customer = fiscalRegistry(['vat_number' => '01234567890', 'tax_code' => 'RSSMRA80A01H501U']);
    installmentOf(installmentInvoice($customer));

    expect(fiscalGridRow('invoices')['customer'])->toBe("{$customer->name} - 01234567890")
        ->and(fiscalGridRow('invoice-installments')['customer'])->toBe("{$customer->name} - 01234567890");
});

it('falls back to the tax code, and to the bare name with neither', function () {
    Sanctum::actingAs(installmentUserWith(['viewAny']));
    $person = fiscalRegistry(['vat_number' => '  ', 'tax_code' => 'RSSMRA80A01H501U']);
    installmentOf(installmentInvoice($person));

    expect(fiscalGridRow('invoice-installments')['customer'])->toBe("{$person->name} - RSSMRA80A01H501U");

    PersonalData::query()->where('personable_id', $person->id)->update(['tax_code' => null]);

    expect(fiscalGridRow('invoice-installments')['customer'])->toBe($person->name);
});

it('shows the supplier VAT number in the payable invoices and their installments', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny'], ['purchase-invoices.viewAny']));
    $supplier = fiscalRegistry(['vat_number' => '09876543210'], supplier: true);
    purchaseInstallmentOf(purchaseDocument($supplier));

    expect(fiscalGridRow('purchase-invoices')['supplier'])->toBe("{$supplier->name} - 09876543210")
        ->and(fiscalGridRow('purchase-invoice-installments')['supplier'])->toBe("{$supplier->name} - 09876543210");
});

it('shows the registry VAT number in the treasury movements', function () {
    Sanctum::actingAs(treasuryUserWith(['viewAny']));
    $registry = fiscalRegistry(['vat_number' => '01234567890']);
    TreasuryMovement::factory()->income()->create(['registry_id' => $registry->id]);

    expect(fiscalGridRow('treasury-movements')['registry'])->toBe("{$registry->name} - 01234567890");
});

it('shows the customer VAT number in the agent commission accruals', function () {
    $user = installmentUserWith([]);
    $user->givePermissionTo(Permission::findOrCreate('commission-accruals.viewAny', 'web'));
    Sanctum::actingAs($user);
    $customer = fiscalRegistry(['vat_number' => '01234567890']);
    CommissionAccrual::factory()->create(['invoice_id' => installmentInvoice($customer)->id]);

    expect(fiscalGridRow('commission-accruals')['customer'])->toBe("{$customer->name} - 01234567890");
});

it('shows customer and supplier with the fiscal id in the proforma requests grid and record', function () {
    Sanctum::actingAs(proformaUserWith(['viewAny', 'view']));
    $supplier = fiscalRegistry(['tax_code' => '80012345678'], supplier: true);
    $request = ProformaRequest::factory()->create(['supplier_id' => $supplier->id]);
    $customer = $request->workOrder->quote->opportunity->registry;
    PersonalData::factory()->for($customer, 'personable')->create(['vat_number' => '01234567890', 'tax_code' => null]);
    $customerLabel = "{$customer->name} - 01234567890";

    $row = fiscalGridRow('proforma-requests');
    expect($row['customer'])->toBe(['id' => $customer->id, 'name' => $customerLabel])
        ->and($row['supplier'])->toBe(['id' => $supplier->id, 'name' => "{$supplier->name} - 80012345678"]);

    $this->getJson("/api/proforma-requests/{$request->id}")->assertOk()
        ->assertJsonPath('data.customer.name', $customer->name)
        ->assertJsonPath('data.customer.label', $customerLabel)
        ->assertJsonPath('data.supplier.label', "{$supplier->name} - 80012345678");
});

it('labels the registry picker with the fiscal id and finds a registry by it', function () {
    Sanctum::actingAs(User::factory()->create());
    $company = fiscalRegistry(['vat_number' => '01234567890']);
    $person = fiscalRegistry(['tax_code' => 'RSSMRA80A01H501U']);

    $labels = collect($this->getJson('/api/registries/for-select')->assertOk()->json('items'))->pluck('label', 'id');
    expect($labels[$company->id])->toBe("{$company->name} - 01234567890")
        ->and($labels[$person->id])->toBe("{$person->name} - RSSMRA80A01H501U");

    $this->getJson('/api/registries/for-select?search=0123456')->assertOk()
        ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $company->id);
    $this->getJson('/api/registries/for-select?search=RSSMRA80')->assertOk()
        ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $person->id);
});

it('presets the proforma issue picker with the customer label', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    $request = ProformaRequest::factory()->create();
    $customer = $request->workOrder->quote->opportunity->registry;
    PersonalData::factory()->for($customer, 'personable')->create(['vat_number' => '01234567890', 'tax_code' => null]);

    $this->getJson("/api/proforma-requests/{$request->id}/invoice-draft")->assertOk()
        ->assertJsonPath('data.defaults.customer.name', $customer->name)
        ->assertJsonPath('data.defaults.customer.label', "{$customer->name} - 01234567890");
});

it('exposes the label on the active and payable invoice records', function () {
    $customer = fiscalRegistry(['tax_code' => 'RSSMRA80A01H501U']);
    $supplier = fiscalRegistry(['vat_number' => '09876543210'], supplier: true);
    $invoice = Invoice::factory()->create(['customer_registry_id' => $customer->id]);
    $document = PurchaseInvoice::factory()->create(['supplier_id' => $supplier->id]);

    Sanctum::actingAs(invoiceUserWith(['view']));
    $this->getJson("/api/invoices/{$invoice->id}")->assertOk()
        ->assertJsonPath('data.customer.label', "{$customer->name} - RSSMRA80A01H501U");

    Sanctum::actingAs(purchaseInvoiceUserWith(['view']));
    $this->getJson("/api/purchase-invoices/{$document->id}")->assertOk()
        ->assertJsonPath('data.supplier.label', "{$supplier->name} - 09876543210");
});

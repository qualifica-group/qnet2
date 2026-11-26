<?php

use App\Models\PersonalData;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0431, AC-003 (supplier registry of the employee, D-4).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    $this->fixture = payslipSetUp();
    $this->user = $this->fixture['users']['a'];
    $this->file = fn () => payslipFile([payslipRow()]);
});

/** A registry holding the given tax code on its card. */
function payslipRegistryWithTaxCode(string $taxCode, bool $supplier): Registry
{
    $registry = Registry::factory()->create(['is_supplier' => $supplier]);
    PersonalData::factory()->individual()->for($registry, 'personable')->create(['tax_code' => $taxCode]);

    return $registry;
}

/** The registry id the single imported document points to. */
function payslipSupplierOfDocument(): int
{
    return (int) PurchaseInvoice::query()->sole()->supplier_id;
}

it('AC-003: a user already linked keeps the same registry, even with another registry holding the tax code', function () {
    $linked = Registry::factory()->supplier()->create();
    $this->user->forceFill(['supplier_registry_id' => $linked->id])->save();
    payslipRegistryWithTaxCode(PAYSLIP_TAX_A, true);
    $registries = Registry::query()->count();

    $response = payslipImport(payslipImporterUser(), ($this->file)(), false)->assertOk();

    expect(payslipSupplierOfDocument())->toBe($linked->id)
        ->and($this->user->fresh()->supplier_registry_id)->toBe($linked->id)
        ->and(Registry::query()->count())->toBe($registries)
        ->and($response->json('data.summary.suppliers_created'))->toBe(0)
        ->and($response->json('data.summary.suppliers_linked'))->toBe(0);
});

it('AC-003: the only registry with the same tax code is linked and marked as supplier', function () {
    $customer = payslipRegistryWithTaxCode(PAYSLIP_TAX_A, false);
    $registries = Registry::query()->count();

    $preview = payslipImport(payslipImporterUser(), ($this->file)(), true)->assertOk();
    expect($preview->json('data.summary.suppliers_linked'))->toBe(1)
        ->and($customer->fresh()->is_supplier)->toBeFalse()
        ->and($this->user->fresh()->supplier_registry_id)->toBeNull();

    payslipImport(payslipImporterUser(), ($this->file)(), false)->assertOk();

    expect(payslipSupplierOfDocument())->toBe($customer->id)
        ->and($customer->fresh()->is_supplier)->toBeTrue()
        ->and($this->user->fresh()->supplier_registry_id)->toBe($customer->id)
        ->and(Registry::query()->count())->toBe($registries);
});

it('AC-003: two registries with the same tax code give a new supplier registry and a warning', function () {
    $first = payslipRegistryWithTaxCode(PAYSLIP_TAX_A, true);
    $second = payslipRegistryWithTaxCode(strtolower(PAYSLIP_TAX_A), false);
    $registries = Registry::query()->count();

    $preview = payslipImport(payslipImporterUser(), ($this->file)(), true)->assertOk();

    expect($preview->json('data.summary.suppliers_created'))->toBe(1)
        ->and($preview->json('data.warnings.0.row'))->toBe(2)
        ->and($preview->json('data.warnings.0.message'))->toContain('Più anagrafiche');

    payslipImport(payslipImporterUser(), ($this->file)(), false)->assertOk();

    $supplier = payslipSupplierOfDocument();
    expect($supplier)->not->toBe($first->id)->not->toBe($second->id)
        ->and($this->user->fresh()->supplier_registry_id)->toBe($supplier)
        ->and(Registry::query()->count())->toBe($registries + 1);
});

it('AC-003: no registry creates a supplier registry with the name and tax code of the user card', function () {
    $card = $this->user->personalData;
    $registries = Registry::query()->count();

    $response = payslipImport(payslipImporterUser(), ($this->file)(), false)->assertOk();

    $registry = Registry::query()->with('personalData')->findOrFail(payslipSupplierOfDocument());
    expect($response->json('data.summary.suppliers_created'))->toBe(1)
        ->and(Registry::query()->count())->toBe($registries + 1)
        ->and($registry->is_supplier)->toBeTrue()
        ->and($registry->personalData->first_name)->toBe($card->first_name)
        ->and($registry->personalData->last_name)->toBe($card->last_name)
        ->and($registry->personalData->tax_code)->toBe(PAYSLIP_TAX_A)
        ->and($this->user->fresh()->supplier_registry_id)->toBe($registry->id);
});

it('AC-003: a person on two companies gets one registry shared by both documents', function () {
    payslipImport(payslipImporterUser(), payslipFile([payslipRow(), payslipRow(['AZIENDA' => '3'])]), false)->assertOk();

    expect(PurchaseInvoice::query()->distinct()->pluck('supplier_id'))->toHaveCount(1)
        ->and(Registry::query()->where('is_supplier', true)->count())->toBe(1);
});

<?php

use App\Enums\PurchaseInvoiceType;
use App\Models\Payslip;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0431, AC-001 (endpoint) and AC-002.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    $this->fixture = payslipSetUp();
});

/**
 * The reference file: one person on two companies, a second person, accented
 * names and a bank description with doubled quotes inside an enclosure.
 *
 * @return list<array<string, string>>
 */
function payslipReferenceRows(): array
{
    return [
        payslipRow(),
        payslipRow(['AZIENDA' => '3', 'CODICE DIPENDENTE' => '9101', 'IMPORTO' => '500,00']),
        payslipRow(['RIP.1: CODICE' => PAYSLIP_TAX_B, 'CODICE DIPENDENTE' => '102', 'COGNOME E NOME' => 'Perù Anna', 'IMPORTO' => '2000,00']),
    ];
}

it('AC-001: the import answers 401 anonymously, 403 without import and 403 with import but without create', function () {
    $file = payslipFile(payslipReferenceRows());

    $this->post('/api/purchase-invoices/payslips/import', ['file' => $file, 'dry_run' => '1', 'payment_date' => PAYSLIP_PAYMENT_DATE], ['Accept' => 'application/json'])->assertUnauthorized();

    payslipImport(User::factory()->create(), $file, true)->assertForbidden();
    payslipImport(User::factory()->create()->givePermissionTo('purchase-invoices.create'), $file, true)->assertForbidden();
    payslipImport(User::factory()->create()->givePermissionTo('purchase-invoices.import'), $file, true)->assertForbidden();
    expect(PurchaseInvoice::query()->count())->toBe(0);
});

it('AC-002: a Windows-1252 tab file previews exactly and writes nothing', function () {
    $registries = Registry::query()->count();

    $preview = payslipImport(payslipImporterUser(), payslipFile(payslipReferenceRows()), true)->assertOk();

    expect($preview->json('data.applied'))->toBeFalse()
        ->and($preview->json('data.period'))->toBe('2026-10')
        ->and($preview->json('data.summary'))->toBe([
            'rows' => 3, 'people' => 2, 'companies' => 2, 'total_net' => 3734.56,
            'created' => 3, 'updated' => 0, 'suppliers_created' => 2, 'suppliers_linked' => 0,
        ])
        ->and($preview->json('data.errors'))->toBe([])
        ->and(PurchaseInvoice::query()->count())->toBe(0)
        ->and(Payslip::query()->count())->toBe(0)
        ->and(Registry::query()->count())->toBe($registries)
        ->and(User::query()->whereNotNull('supplier_registry_id')->count())->toBe(0);
});

it('AC-002: dry_run=false creates one payslip document per row with a single installment', function () {
    $actor = payslipImporterUser();

    $applied = payslipImport($actor, payslipFile(payslipReferenceRows()), false)->assertOk();

    expect($applied->json('data.applied'))->toBeTrue()
        ->and(PurchaseInvoice::query()->count())->toBe(3)
        ->and(Payslip::query()->count())->toBe(3);

    $payslip = Payslip::query()->where('company_id', $this->fixture['companies']['1']->id)->where('user_id', $this->fixture['users']['a']->id)->sole();
    $document = PurchaseInvoice::query()->with(['lines.costType', 'installments', 'paymentMethod'])->findOrFail($payslip->purchase_invoice_id);

    expect($payslip->period)->toBe('2026-10')
        ->and($payslip->payslip_type)->toBe('MENSILITA_NORMALE')
        ->and($payslip->employee_code)->toBe('101')
        ->and($document->document_type)->toBe(PurchaseInvoiceType::Payslip)
        ->and($document->document_date->toDateString())->toBe('2026-10-31')
        ->and($document->document_number)->toBeNull()
        ->and($document->financial_account_id)->toBeNull()
        ->and($document->created_by)->toBe($actor->id)
        ->and($document->paymentMethod->code)->toBe('bonifico_bancario')
        ->and($document->supplier_id)->toBe($this->fixture['users']['a']->fresh()->supplier_registry_id)
        ->and((string) $document->net_amount)->toBe('1234.56')
        ->and((string) $document->vat_amount)->toBe('0.00')
        ->and((string) $document->total_amount)->toBe('1234.56')
        ->and($document->lines)->toHaveCount(1)
        ->and($document->lines[0]->description)->toBe('Retribuzione netta ottobre 2026')
        ->and($document->lines[0]->vat_rate_id)->toBeNull()
        ->and($document->lines[0]->work_order_id)->toBeNull()
        ->and($document->lines[0]->costType->code)->toBe('63.01.001')
        ->and((string) $document->lines[0]->unit_price)->toBe('1234.56')
        ->and($document->installments)->toHaveCount(1)
        ->and((string) $document->installments[0]->amount)->toBe('1234.56')
        ->and((string) $document->installments[0]->residual_amount)->toBe('1234.56')
        ->and($document->installments[0]->due_date->toDateString())->toBe(PAYSLIP_PAYMENT_DATE);

    $second = Payslip::query()->where('company_id', $this->fixture['companies']['3']->id)->sole();
    expect($second->employee_code)->toBe('9101');
});

it('AC-002: the same content as comma csv produces the same documents', function () {
    payslipImport(payslipImporterUser(), payslipFile(payslipReferenceRows(), 'net.csv', delimiter: ','), false)->assertOk();

    expect(Payslip::query()->count())->toBe(3)
        ->and((float) PurchaseInvoice::query()->sum('total_amount'))->toBe(3734.56);
});

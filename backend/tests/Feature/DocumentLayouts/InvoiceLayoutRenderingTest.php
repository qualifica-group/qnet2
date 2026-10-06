<?php

declare(strict_types=1);

use App\Enums\DocumentLayoutModule;
use App\Models\Company;
use App\Models\DocumentLayout;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Models\PaymentMethod;
use App\Models\Registry;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;
use App\Services\DocumentLayouts\Rendering\DocumentGenerator;
use App\Services\DocumentLayouts\Rendering\Exceptions\InvalidDocumentLayoutConfigException;
use App\Services\DocumentLayouts\Rendering\Invoices\InvoiceRenderSubject;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// spec 0195 AC-002 — layouts of the `invoices` module.

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function invLayoutText(string $id, string $text): array
{
    return [
        'id' => $id, 'type' => 'text', 'align' => 'left', 'space_before' => 0, 'space_after' => 0, 'line_height' => 1.0,
        'runs' => [['text' => $text, 'field' => null, 'bold' => false, 'italic' => false, 'underline' => false, 'font' => null, 'size' => null, 'color' => null]],
    ];
}

/**
 * @param  array<int, string>  $keys
 * @param  array<int, array<string, mixed>>|null  $totalsRows
 * @return array<string, mixed>
 */
function invLayoutTable(string $id, string $source, array $keys, ?array $totalsRows = null): array
{
    $width = intdiv(100, count($keys));
    $columns = array_map(static fn (string $key): array => [
        'lines' => [['keys' => [$key], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]],
        'label' => $key, 'width_pct' => $width, 'align' => 'left',
    ], $keys);
    $columns[0]['width_pct'] += 100 - $width * count($keys);

    return [
        'id' => $id, 'type' => 'products_table', 'source' => $source, 'width_pct' => 100, 'borders' => null,
        'show_header' => true, 'header_background' => null, 'empty_text' => null, 'columns' => $columns,
        'totals' => $totalsRows === null ? ['show' => false, 'rows' => []] : ['show' => true, 'rows' => $totalsRows],
    ];
}

/**
 * @return array<string, mixed>
 */
function invLayoutConfig(): array
{
    return array_replace(DocumentLayoutFactory::minimalConfig(), [
        'body' => ['blocks' => [
            invLayoutText('t1', 'Doc {invoice.number_label} for {customer.name} IBAN {payment.iban} total {totals.total} residual {totals.residual}'),
            invLayoutTable('p1', 'invoice_lines', ['description', 'quantity', 'total_amount'], [['label' => 'Total', 'variable' => '{totals.total}', 'bold' => true]]),
            invLayoutTable('p2', 'installments', ['sequence', 'due_date', 'amount', 'status']),
        ]],
    ]);
}

function invLayoutDocumentText(string $docx): string
{
    $path = tempnam(sys_get_temp_dir(), 'invlayout').'.docx';
    file_put_contents($path, $docx);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)));
}

it('AC-002: renders invoice_lines and installments plus the invoice variables', function (): void {
    $layout = DocumentLayout::factory()->create(['module' => 'invoices', 'config' => invLayoutConfig()]);
    $invoice = Invoice::factory()->create([
        'number' => 12, 'year' => 2026, 'net_amount' => '100.00', 'vat_amount' => '22.00', 'total_amount' => '122.00',
        'customer_registry_id' => Registry::factory()->create(['name' => 'Acme Customer']),
        'financial_account_id' => FinancialAccount::factory()->create(['iban' => 'IT60X0542811101000000123456']),
    ]);
    InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'description' => 'Consulting day', 'quantity' => '2', 'total_amount' => '122.00']);
    InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => '122.00', 'due_date' => '2026-09-30', 'collected_amount' => '22.00']);

    $docx = app(DocumentGenerator::class)->generate(InvoiceRenderSubject::for($invoice), $layout, User::factory()->create());
    $text = invLayoutDocumentText($docx);

    expect($text)->toContain('Doc 12/2026 for Acme Customer')
        ->and($text)->toContain('IBAN IT60X0542811101000000123456')
        ->and($text)->toContain('total 122,00')
        ->and($text)->toContain('residual 100,00')
        ->and($text)->toContain('Consulting day')
        ->and($text)->toContain('30/09/2026')
        ->and($text)->toContain(__('document_layouts.installment_status.partially_paid'))
        ->and($text)->not->toMatch('/\{[a-z_]+\.[a-z_]+\}/');
});

it('AC-002: an unknown invoices token renders as empty string', function (): void {
    $config = array_replace(DocumentLayoutFactory::minimalConfig(), ['body' => ['blocks' => [invLayoutText('t1', 'X{invoice.nope}Y')]]]);
    $layout = DocumentLayout::factory()->create(['module' => 'invoices', 'config' => $config]);

    $docx = app(DocumentGenerator::class)->generate(InvoiceRenderSubject::for(Invoice::factory()->create()), $layout, User::factory()->create());

    expect(invLayoutDocumentText($docx))->toContain('XY');
});

it('AC-002: offer_lines on an invoices layout is rejected at generation', function (): void {
    $config = array_replace(DocumentLayoutFactory::minimalConfig(), ['body' => ['blocks' => [invLayoutTable('p1', 'offer_lines', ['code'])]]]);
    $layout = DocumentLayout::factory()->create(['module' => 'invoices', 'config' => $config]);

    expect(fn () => app(DocumentGenerator::class)->generate(InvoiceRenderSubject::for(Invoice::factory()->create()), $layout, User::factory()->create()))
        ->toThrow(InvalidDocumentLayoutConfigException::class);
});

it('AC-002: the layouts store endpoint answers 422 for offer_lines on module invoices and 201 for invoice_lines', function (): void {
    Permission::findOrCreate('document-layouts.create');
    Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo('document-layouts.create'));

    $bad = array_replace(DocumentLayoutFactory::minimalConfig(), ['body' => ['blocks' => [invLayoutTable('p1', 'offer_lines', ['code'])]]]);
    $this->postJson('/api/document-layouts', ['name' => 'Bad', 'code' => 'bad_invoice', 'module' => 'invoices', 'is_active' => true, 'config' => $bad])
        ->assertUnprocessable();

    $this->postJson('/api/document-layouts', ['name' => 'Good', 'code' => 'good_invoice', 'module' => 'invoices', 'is_active' => true, 'config' => invLayoutConfig()])
        ->assertCreated()
        ->assertJsonPath('data.module', 'invoices');
});

it('exposes the invoices variable catalogue with the frozen tokens', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('super-admin'));

    $categories = collect(app(DocumentLayoutVariableCatalog::class)->categoriesFor(DocumentLayoutModule::Invoices, $actor));
    $tokens = $categories->flatMap(fn (array $category): array => array_column($category['variables'], 'variable'))->all();

    expect($tokens)->toContain('{invoice.number_label}', '{customer.pec}', '{payment.iban}', '{totals.residual}', '{company.name}', '{company_site.bank_iban}', '{work_order.code}', '{quote.code}');
});

it('resolves company, payment method and work order tokens', function (): void {
    $invoice = Invoice::factory()->create([
        'company_id' => Company::factory()->create(['denomination' => 'Issuer Srl']),
        'payment_method_id' => PaymentMethod::factory()->create(['name' => 'Wire 30', 'payment_instructions' => 'Pay by wire']),
    ]);
    $subject = InvoiceRenderSubject::for($invoice);
    $subject->prepare();
    $actor = User::factory()->create();

    expect($subject->resolveVariable('company', 'name', $actor))->toBe('Issuer Srl')
        ->and($subject->resolveVariable('payment', 'method_name', $actor))->toBe('Wire 30')
        ->and($subject->resolveVariable('payment', 'payment_instructions', $actor))->toBe('Pay by wire')
        ->and($subject->resolveVariable('work_order', 'code', $actor))->toBe('')
        ->and($subject->resolveVariable('nope', 'nope', $actor))->toBe('');
});

it('D-9: previews an invoices layout against an in-memory sample when no invoice exists, and a real one by invoice_id', function (): void {
    $layout = DocumentLayout::factory()->create(['module' => 'invoices', 'config' => invLayoutConfig()]);
    $docx = captureDocxToPdfConversion();
    foreach (['view', 'viewAny'] as $ability) {
        Permission::findOrCreate("document-layouts.{$ability}");
    }
    Permission::findOrCreate('invoices.view');
    Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo(['document-layouts.view', 'invoices.view']));

    $this->postJson("/api/document-layouts/{$layout->id}/preview")->assertOk();
    expect(invLayoutDocumentText($docx()))->toContain('Sample customer S.r.l.')->and(Invoice::count())->toBe(0);

    $invoice = Invoice::factory()->create(['number' => 5, 'year' => 2026]);
    $this->postJson("/api/document-layouts/{$layout->id}/preview", ['invoice_id' => $invoice->id])->assertOk();
    expect(invLayoutDocumentText($docx()))->toContain('Doc 5/2026');

    $this->postJson("/api/document-layouts/{$layout->id}/preview", ['invoice_id' => 999999])->assertNotFound();
});

<?php

declare(strict_types=1);

use App\Enums\DocumentLayoutModule;
use App\Enums\PurchaseOrderStatus;
use App\Models\DocumentLayout;
use App\Models\Registry;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;
use App\Services\DocumentLayouts\Rendering\DocumentGenerator;
use App\Services\DocumentLayouts\Rendering\PurchaseOrders\PurchaseOrderRenderSubject;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// spec 0210 AC-013 — GET /api/purchase-orders/{purchaseOrder}/pdf and the purchase_orders layout module.

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function poLayoutConfig(): array
{
    $text = [
        'id' => 't1', 'type' => 'text', 'align' => 'left', 'space_before' => 0, 'space_after' => 0, 'line_height' => 1.0,
        'runs' => [['text' => 'ODA {purchase_order.code} to {supplier.name} total {totals.total}', 'field' => null, 'bold' => false, 'italic' => false, 'underline' => false, 'font' => null, 'size' => null, 'color' => null]],
    ];
    $keys = ['description', 'cost_type', 'quantity', 'total_amount'];
    $table = [
        'id' => 'p1', 'type' => 'products_table', 'source' => 'purchase_order_lines', 'width_pct' => 100, 'borders' => null,
        'show_header' => true, 'header_background' => null, 'empty_text' => null,
        'columns' => array_map(static fn (string $key): array => [
            'lines' => [['keys' => [$key], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]],
            'label' => $key, 'width_pct' => 25, 'align' => 'left',
        ], $keys),
        'totals' => ['show' => true, 'rows' => [['label' => 'Total', 'variable' => '{totals.total}', 'bold' => true]]],
    ];

    return array_replace(DocumentLayoutFactory::minimalConfig(), ['body' => ['blocks' => [$text, $table]]]);
}

function poDocumentText(string $docx): string
{
    $path = tempnam(sys_get_temp_dir(), 'polayout').'.docx';
    file_put_contents($path, $docx);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)));
}

it('AC-013: 200 application/pdf with the active default layout and the code in the filename', function (): void {
    DocumentLayout::factory()->create(['module' => 'purchase_orders', 'is_default' => true, 'config' => DocumentLayoutFactory::minimalConfig()]);
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Draft, 1, ['number' => 7, 'year' => 2026]);
    $convertedDocx = captureDocxToPdfConversion();
    Sanctum::actingAs(purchaseOrderUserWith(['view']));

    $response = $this->get("/api/purchase-orders/{$order->id}/pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('ODA-7-2026.pdf')
        ->and($response->streamedContent())->toStartWith('%PDF-')
        ->and($convertedDocx())->toStartWith('PK');
});

it('AC-013: 422 with the contract message when no active default layout exists', function (): void {
    DocumentLayout::factory()->create(['module' => 'purchase_orders', 'is_default' => false]);
    DocumentLayout::factory()->create(['module' => 'invoices', 'is_default' => true]);
    $order = purchaseOrderWithLines();
    Sanctum::actingAs(purchaseOrderUserWith(['view']));

    $this->getJson("/api/purchase-orders/{$order->id}/pdf")
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No document layout available for purchase orders.');
});

it('AC-013: 403 without purchase-orders.view and 404 on an unknown order', function (): void {
    DocumentLayout::factory()->create(['module' => 'purchase_orders', 'is_default' => true]);
    $order = purchaseOrderWithLines();
    Sanctum::actingAs(purchaseOrderUserWith(['send']));

    $this->getJson("/api/purchase-orders/{$order->id}/pdf")->assertForbidden();

    Sanctum::actingAs(purchaseOrderUserWith(['view']));

    $this->getJson('/api/purchase-orders/999999/pdf')->assertNotFound();
});

it('AC-013: renders the purchase order variables, lines and totals', function (): void {
    $layout = DocumentLayout::factory()->create(['module' => 'purchase_orders', 'config' => poLayoutConfig()]);
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Draft, 1, [
        'number' => 12, 'year' => 2026,
        'supplier_id' => Registry::factory()->supplier()->create(['name' => 'Acme Supplier']),
    ]);

    $docx = app(DocumentGenerator::class)->generate(PurchaseOrderRenderSubject::for($order), $layout, User::factory()->create());
    $text = poDocumentText($docx);

    expect($text)->toContain('ODA 12/2026 to Acme Supplier')
        ->and($text)->toContain('total 100,00')
        ->and($text)->toContain($order->lines->first()->description)
        ->and($text)->not->toMatch('/\{[a-z_]+\.[a-z_]+\}/');
});

it('AC-013: the layouts store endpoint accepts the purchase_orders module and rejects foreign sources', function (): void {
    Permission::findOrCreate('document-layouts.create');
    Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo('document-layouts.create'));

    $this->postJson('/api/document-layouts', ['name' => 'Good', 'code' => 'good_po', 'module' => 'purchase_orders', 'is_active' => true, 'config' => poLayoutConfig()])
        ->assertCreated()
        ->assertJsonPath('data.module', 'purchase_orders');

    $bad = poLayoutConfig();
    $bad['body']['blocks'][1]['source'] = 'invoice_lines';

    $this->postJson('/api/document-layouts', ['name' => 'Bad', 'code' => 'bad_po', 'module' => 'purchase_orders', 'is_active' => true, 'config' => $bad])
        ->assertUnprocessable();
});

it('exposes the purchase_orders variable catalogue', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('super-admin'));

    $categories = collect(app(DocumentLayoutVariableCatalog::class)->categoriesFor(DocumentLayoutModule::PurchaseOrders, $actor));
    $tokens = $categories->flatMap(fn (array $category): array => array_column($category['variables'], 'variable'))->all();

    expect($tokens)->toContain(
        '{purchase_order.code}', '{purchase_order.order_date}', '{supplier.name}', '{supplier.vat_number}', '{supplier.email}',
        '{company.name}', '{company_site.name}', '{operational_site.label}', '{payment.method_name}', '{customer.name}',
        '{work_order.code}', '{totals.net}', '{totals.vat}', '{totals.total}',
    );
});

it('AC-013: the layout preview renders a purchase_orders layout against a sample when no order exists', function (): void {
    $layout = DocumentLayout::factory()->create(['module' => 'purchase_orders', 'config' => poLayoutConfig()]);
    $convertedDocx = captureDocxToPdfConversion();
    Permission::findOrCreate('document-layouts.view');
    Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo('document-layouts.view'));

    $this->post("/api/document-layouts/{$layout->id}/preview")->assertOk();

    expect(poDocumentText($convertedDocx()))->toContain('ODA 1/')->toContain('Sample supplier S.r.l.')->toContain('Sample item A');
});

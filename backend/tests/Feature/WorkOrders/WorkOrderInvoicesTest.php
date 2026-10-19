<?php

use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PurchaseInvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseInvoiceLine;
use App\Models\WorkOrder;
use App\Policies\WorkOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Commessa proforma/invoices view (spec 0282): GET /api/work-orders/{workOrder}/invoices.
 */
uses(RefreshDatabase::class);

function invoicesUrl(WorkOrder $workOrder): string
{
    return "/api/work-orders/{$workOrder->id}/invoices";
}

function passiveDocumentOn(WorkOrder $workOrder, array $lineAmounts, array $attributes = []): PurchaseInvoice
{
    $document = PurchaseInvoice::factory()->create($attributes);

    foreach ($lineAmounts as $amount) {
        PurchaseInvoiceLine::factory()->create([
            'purchase_invoice_id' => $document->id,
            'work_order_id' => $amount[0] === null ? null : ($amount[0] === 'this' ? $workOrder->id : WorkOrder::factory()->create()->id),
            'total_amount' => $amount[1],
        ]);
    }

    return $document;
}

it('AC-001: active lists exactly the commessa documents, newest first, with the fixed shape', function () {
    $workOrder = WorkOrder::factory()->create();
    $proforma = Invoice::factory()->create(['work_order_id' => $workOrder->id, 'document_date' => '2026-09-01']);
    $invoice = Invoice::factory()->asInvoice()->create(['work_order_id' => $workOrder->id, 'document_date' => '2026-10-01']);
    Invoice::factory()->create(['work_order_id' => WorkOrder::factory()->create()->id]);
    Sanctum::actingAs(workOrderInvoicesUserWith(['viewInvoices']));

    $response = $this->getJson(invoicesUrl($workOrder))->assertOk()->assertJsonPath('success', true);

    expect(array_column($response->json('data.active'), 'id'))->toBe([$invoice->id, $proforma->id]);
    $row = $response->json('data.active.0');
    expect(array_keys($row))->toBe([
        'id', 'type', 'number_label', 'document_date', 'external_number', 'external_date', 'customer', 'company',
        'net_amount', 'vat_amount', 'total_amount', 'collected_amount', 'residual_amount', 'payment_status',
    ]);
    expect($row['type'])->toBe('invoice')
        ->and($row['number_label'])->toBe("{$invoice->number}/{$invoice->year}")
        ->and($row['document_date'])->toBe('2026-10-01')
        ->and($row['customer']['id'])->toBe($invoice->customer_registry_id)
        ->and($response->json('data.active.1.type'))->toBe('proforma');
});

it('AC-002: passive lists documents with at least one line on the commessa and sums only those lines', function () {
    $workOrder = WorkOrder::factory()->create();
    $included = passiveDocumentOn($workOrder, [['this', '100.00'], ['this', '50.00'], ['other', '30.00']], ['total_amount' => '180.00']);
    // Commessa only on the header: excluded (D-2).
    $headerOnly = PurchaseInvoice::factory()->create(['work_order_id' => $workOrder->id]);
    PurchaseInvoiceLine::factory()->create(['purchase_invoice_id' => $headerOnly->id, 'work_order_id' => null]);
    Sanctum::actingAs(workOrderInvoicesUserWith(['viewInvoices']));

    $response = $this->getJson(invoicesUrl($workOrder))->assertOk();

    expect($response->json('data.passive'))->toHaveCount(1);
    $row = $response->json('data.passive.0');
    expect(array_keys($row))->toBe([
        'id', 'document_type', 'document_number', 'document_date', 'supplier', 'company',
        'total_amount', 'work_order_amount', 'paid_amount', 'residual_amount',
    ]);
    expect($row['id'])->toBe($included->id)
        ->and($row['total_amount'])->toBe('180.00')
        ->and($row['work_order_amount'])->toBe('150.00')
        ->and($response->json('data.totals.passive.work_order_amount'))->toBe('150.00');
});

it('AC-003: collected/paid/residual/status match the resources and totals are the row sums', function () {
    $workOrder = WorkOrder::factory()->create();
    $invoice = Invoice::factory()->asInvoice()->create(['work_order_id' => $workOrder->id, 'total_amount' => '1000.00']);
    InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => '600.00', 'collected_amount' => '250.00', 'collected_at' => now()->toDateString()]);
    InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 2, 'amount' => '400.00', 'due_date' => now()->subDays(30)->toDateString()]);
    $second = Invoice::factory()->create(['work_order_id' => $workOrder->id, 'total_amount' => '200.00']);
    $document = passiveDocumentOn($workOrder, [['this', '100.00']], ['total_amount' => '100.00', 'withholding_amount' => '10.00']);
    PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $document->id, 'amount' => '90.00', 'paid_amount' => '40.00', 'paid_at' => now()->toDateString()]);
    Sanctum::actingAs(workOrderInvoicesUserWith(['viewInvoices']));

    $data = $this->getJson(invoicesUrl($workOrder))->assertOk()->json('data');

    $expectedInvoice = (new InvoiceResource($invoice->fresh(['company', 'customer.personalData', 'paymentMethod', 'financialAccount', 'proformaRequest', 'workOrder', 'quote', 'invoiceTag', 'layout', 'lines.product', 'lines.vatRate', 'installments', 'createdBy'])))->resolve();
    $row = collect($data['active'])->firstWhere('id', $invoice->id);
    expect($row['collected_amount'])->toBe($expectedInvoice['collected_amount'])->toBe('250.00')
        ->and($row['residual_amount'])->toBe($expectedInvoice['residual_amount'])->toBe('750.00')
        ->and($row['payment_status'])->toBe($expectedInvoice['payment_status']->value);

    $expectedDocument = (new PurchaseInvoiceResource($document->fresh(['company', 'supplier', 'paymentMethod', 'financialAccount', 'companySite', 'operationalSite', 'costType', 'workOrder', 'commissionSettlement', 'purchaseOrders', 'lines', 'installments.financialAccount', 'createdBy'])))->resolve();
    $passive = $data['passive'][0];
    expect($passive['paid_amount'])->toBe($expectedDocument['paid_amount'])->toBe('40.00')
        ->and($passive['residual_amount'])->toBe($expectedDocument['residual_amount'])->toBe('50.00');

    expect($data['totals']['active'])->toBe([
        'total_amount' => '1200.00',
        'collected_amount' => '250.00',
        'residual_amount' => '950.00',
    ])->and($data['totals']['passive']['paid_amount'])->toBe('40.00');
    expect($second->id)->toBeInt();
});

it('AC-004: 403 without viewInvoices even with the module viewAny permissions', function () {
    $workOrder = WorkOrder::factory()->create();
    foreach (['invoices.viewAny', 'purchase-invoices.viewAny'] as $name) {
        Permission::findOrCreate($name);
    }
    $user = workOrderInvoicesUserWith([]);
    $user->givePermissionTo(['invoices.viewAny', 'purchase-invoices.viewAny']);
    Sanctum::actingAs($user);

    $this->getJson(invoicesUrl($workOrder))->assertForbidden()->assertJsonPath('success', false);
});

it('AC-004: 403 on a commessa outside the user scope, 404 on a missing one, 401 unauthenticated', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderInvoicesUserWith(['viewInvoices'], viewAll: false));

    $this->getJson(invoicesUrl($workOrder))->assertForbidden();
    $this->getJson('/api/work-orders/999999/invoices')->assertNotFound();

    $inScope = workOrderInvoicesUserWith(['viewInvoices'], viewAll: false);
    $workOrder->supervisors()->attach($inScope->id);
    Sanctum::actingAs($inScope);
    $this->getJson(invoicesUrl($workOrder))->assertOk();

    $this->app['auth']->forgetGuards();
    $this->getJson(invoicesUrl($workOrder))->assertUnauthorized();
});

it('AC-005: an empty commessa returns empty lists, zero totals and writes nothing', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderInvoicesUserWith(['viewInvoices']));
    $before = [DB::table('invoices')->count(), DB::table('purchase_invoices')->count(), DB::table('activity_log')->count()];

    $data = $this->getJson(invoicesUrl($workOrder))->assertOk()->json('data');

    expect($data['active'])->toBe([])
        ->and($data['passive'])->toBe([])
        ->and($data['totals'])->toBe([
            'active' => ['total_amount' => '0.00', 'collected_amount' => '0.00', 'residual_amount' => '0.00'],
            'passive' => ['total_amount' => '0.00', 'work_order_amount' => '0.00', 'paid_amount' => '0.00', 'residual_amount' => '0.00'],
        ]);
    expect([DB::table('invoices')->count(), DB::table('purchase_invoices')->count(), DB::table('activity_log')->count()])->toBe($before);
});

it('AC-006: view_invoices action and policy ability follow the permission', function () {
    $workOrder = WorkOrder::factory()->create();

    Sanctum::actingAs(workOrderInvoicesUserWith(['view', 'viewInvoices']));
    $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->assertJsonPath('permissions.actions.view_invoices', true);

    Sanctum::actingAs(workOrderInvoicesUserWith(['view']));
    $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->assertJsonPath('permissions.actions.view_invoices', false);

    expect(WorkOrderPolicy::abilities())->toContain('viewInvoices');
    $this->artisan('permissions:sync')->assertSuccessful();
    expect(Permission::where('name', 'work-orders.viewInvoices')->exists())->toBeTrue();
});

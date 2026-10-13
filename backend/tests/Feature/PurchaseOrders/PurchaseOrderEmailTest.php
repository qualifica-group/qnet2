<?php

declare(strict_types=1);

use App\Enums\EmailTemplateModule;
use App\Enums\PurchaseOrderStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Models\Contact;
use App\Models\DocumentLayout;
use App\Models\EmailTemplate;
use App\Models\OutboundEmail;
use App\Models\PersonalData;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0210 AC-014 — purchase order emails (same family as invoice emails, no reminder).

uses(RefreshDatabase::class);

function purchaseOrderWithSupplierEmail(): PurchaseOrder
{
    $supplier = Registry::factory()->supplier()->create(['name' => 'Acme Supplier']);
    $card = PersonalData::factory()->for($supplier, 'personable')->create();
    Contact::factory()->email()->primary()->for($card, 'contactable')->create(['value' => 'orders@supplier.test']);

    return purchaseOrderWithLines(PurchaseOrderStatus::Approved, 1, ['supplier_id' => $supplier->id, 'number' => 5, 'year' => 2026]);
}

function purchaseOrdersDefaultLayout(): DocumentLayout
{
    captureDocxToPdfConversion();

    return DocumentLayout::factory()->create(['module' => 'purchase_orders', 'is_default' => true, 'config' => DocumentLayoutFactory::minimalConfig()]);
}

it('AC-014: compose-context proposes the supplier email and lists the sources', function () {
    $order = purchaseOrderWithSupplierEmail();
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));

    $this->getJson("/api/purchase-orders/{$order->id}/emails/compose-context")
        ->assertOk()
        ->assertJsonPath('data.default_to', ['orders@supplier.test'])
        ->assertJsonPath('data.sources', ['purchase_order_pdf', 'documents'])
        ->assertJsonPath('data.documents', []);
});

it('AC-014: store with attach_pdf creates a draft with one PDF attachment, and send queues the job', function () {
    purchaseOrdersDefaultLayout();
    $order = purchaseOrderWithSupplierEmail();
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));
    Queue::fake();

    $id = $this->postJson("/api/purchase-orders/{$order->id}/emails", [
        'to' => ['orders@supplier.test'], 'subject' => 'Order 5/2026', 'body' => '<p>Hello</p>', 'attach_pdf' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.purpose', 'document')
        ->assertJsonCount(1, 'data.attachments')
        ->assertJsonPath('data.attachments.0.original_name', 'ODA-5-2026.pdf')
        ->json('data.id');

    $this->getJson("/api/purchase-orders/{$order->id}/emails")->assertOk()->assertJsonPath('data.0.id', $id);
    $this->postJson("/api/purchase-orders/{$order->id}/emails/{$id}/send")->assertStatus(202);
    Queue::assertPushed(SendOutboundEmailJob::class);
});

it('AC-014: store without a layout is a 422 and leaves no draft; attach_pdf=false skips the PDF', function () {
    $order = purchaseOrderWithSupplierEmail();
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));

    $this->postJson("/api/purchase-orders/{$order->id}/emails", ['subject' => 'x'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No document layout available for purchase orders.');
    expect(OutboundEmail::query()->count())->toBe(0);

    $this->postJson("/api/purchase-orders/{$order->id}/emails", ['subject' => 'x', 'attach_pdf' => false])
        ->assertCreated()
        ->assertJsonCount(0, 'data.attachments');
});

it('AC-014: attachments/import accepts the purchase_order_pdf source only', function () {
    purchaseOrdersDefaultLayout();
    $order = purchaseOrderWithSupplierEmail();
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));
    $id = $this->postJson("/api/purchase-orders/{$order->id}/emails", ['attach_pdf' => false])->json('data.id');

    $this->postJson("/api/purchase-orders/{$order->id}/emails/{$id}/attachments/import", ['source' => 'purchase_order_pdf'])
        ->assertCreated()
        ->assertJsonCount(1, 'data.attachments');
    $this->postJson("/api/purchase-orders/{$order->id}/emails/{$id}/attachments/import", ['source' => 'invoice_pdf'])
        ->assertUnprocessable();
});

it('AC-014: 403 without purchase-orders.send (write) and without purchase-orders.view (read)', function () {
    $order = purchaseOrderWithSupplierEmail();
    Sanctum::actingAs(purchaseOrderUserWith(['view']));

    $this->postJson("/api/purchase-orders/{$order->id}/emails", [])->assertForbidden();
    $this->getJson("/api/purchase-orders/{$order->id}/emails")->assertOk();
    $this->getJson("/api/purchase-orders/{$order->id}/emails/compose-context")->assertForbidden();

    Sanctum::actingAs(purchaseOrderUserWith(['send']));

    $this->getJson("/api/purchase-orders/{$order->id}/emails")->assertForbidden();
});

it('AC-014: an email of another order is a 404 (scoped binding)', function () {
    $order = purchaseOrderWithSupplierEmail();
    $other = purchaseOrderWithLines(PurchaseOrderStatus::Approved);
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));
    $id = $this->postJson("/api/purchase-orders/{$order->id}/emails", ['attach_pdf' => false])->json('data.id');

    $this->getJson("/api/purchase-orders/{$other->id}/emails/{$id}")->assertNotFound();
});

it('AC-014: render-template resolves the order variables', function () {
    $order = purchaseOrderWithSupplierEmail();
    $template = EmailTemplate::factory()->create([
        'module' => EmailTemplateModule::PurchaseOrders,
        'subject' => 'Order {purchase_order.code}',
        'body' => '<p>Dear {supplier.name}, total {totals.total}</p>',
    ]);
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));

    $this->postJson("/api/purchase-orders/{$order->id}/emails/render-template", ['email_template_id' => $template->id])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Order 5/2026')
        ->assertJsonPath('data.body', '<p>Dear Acme Supplier, total 100,00</p>');
});

it('AC-014: the email-templates variables endpoint accepts the purchase_orders module', function () {
    Permission::findOrCreate('email-templates.view');
    Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo('email-templates.view'));

    $response = $this->getJson('/api/email-templates/variables?module=purchase_orders')->assertOk();
    $tokens = collect($response->json('data'))->flatMap(fn (array $category): array => array_column($category['variables'], 'variable'))->all();

    expect($tokens)->toContain('{purchase_order.code}', '{supplier.name}', '{totals.total}', '{sender.email}');
});

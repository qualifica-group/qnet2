<?php

declare(strict_types=1);

use App\Enums\EmailTemplateModule;
use App\Enums\OutboundEmailPurpose;
use App\Jobs\SendOutboundEmailJob;
use App\Models\Contact;
use App\Models\DocumentLayout;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\OutboundEmail;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0195 AC-004 (invoice email) and AC-005 (payment reminder).

uses(RefreshDatabase::class);

function invoiceEmailUser(array $abilities): User
{
    foreach (['viewAny', 'view', 'viewEmails', 'sendEmail', 'viewDocuments'] as $ability) {
        Permission::findOrCreate("invoices.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("invoices.{$ability}");
    }

    return $user;
}

function invoiceWithCustomerContacts(): Invoice
{
    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->for($registry, 'personable')->create();
    Contact::factory()->email()->primary()->for($card, 'contactable')->create(['value' => 'info@customer.test']);
    Contact::factory()->pec()->primary()->for($card, 'contactable')->create(['value' => 'customer@pec.test']);

    return Invoice::factory()->create(['customer_registry_id' => $registry->id, 'number' => 5, 'year' => 2026]);
}

function invoicesDefaultLayout(): DocumentLayout
{
    captureDocxToPdfConversion();

    return DocumentLayout::factory()->create(['module' => 'invoices', 'is_default' => true, 'config' => DocumentLayoutFactory::minimalConfig()]);
}

function overdueInvoice(): Invoice
{
    $invoice = invoiceWithCustomerContacts();
    InvoiceInstallment::factory()->create([
        'invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => '100.00',
        'due_date' => Carbon::today()->subDays(5)->toDateString(),
    ]);
    InvoiceInstallment::factory()->create([
        'invoice_id' => $invoice->id, 'sequence' => 2, 'amount' => '50.00',
        'due_date' => Carbon::today()->addDays(30)->toDateString(),
    ]);

    return $invoice;
}

it('AC-004: compose-context defaults to the primary PEC, else the primary email, and lists the sources', function () {
    $invoice = invoiceWithCustomerContacts();
    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail']));

    $this->getJson("/api/invoices/{$invoice->id}/emails/compose-context")
        ->assertOk()
        ->assertJsonPath('data.default_to', ['customer@pec.test'])
        ->assertJsonPath('data.quote_pdf_available', false)
        ->assertJsonPath('data.sources', ['invoice_pdf', 'documents'])
        ->assertJsonPath('data.documents', []);

    Contact::query()->where('value', 'customer@pec.test')->delete();

    $this->getJson("/api/invoices/{$invoice->id}/emails/compose-context")
        ->assertOk()
        ->assertJsonPath('data.default_to', ['info@customer.test']);
});

it('AC-004: store with attach_pdf creates a draft with one PDF attachment, and send queues the job', function () {
    invoicesDefaultLayout();
    $invoice = invoiceWithCustomerContacts();
    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail', 'viewEmails']));
    Queue::fake();

    $id = $this->postJson("/api/invoices/{$invoice->id}/emails", [
        'to' => ['customer@pec.test'], 'subject' => 'Proforma', 'body' => '<p>Hello</p>', 'attach_pdf' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.purpose', 'document')
        ->assertJsonCount(1, 'data.attachments')
        ->json('data.id');

    $this->postJson("/api/invoices/{$invoice->id}/emails/{$id}/send")->assertStatus(202);
    Queue::assertPushed(SendOutboundEmailJob::class);
});

it('AC-004: store without a layout is a 422 and leaves no draft; attach_pdf=false skips the PDF', function () {
    $invoice = invoiceWithCustomerContacts();
    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail']));

    $this->postJson("/api/invoices/{$invoice->id}/emails", ['subject' => 'x'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No document layout available for invoices.');
    expect(OutboundEmail::query()->count())->toBe(0);

    $this->postJson("/api/invoices/{$invoice->id}/emails", ['subject' => 'x', 'attach_pdf' => false])
        ->assertCreated()
        ->assertJsonCount(0, 'data.attachments');
});

it('AC-004: attachments/import accepts the invoice_pdf source', function () {
    invoicesDefaultLayout();
    $invoice = invoiceWithCustomerContacts();
    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail']));
    $id = $this->postJson("/api/invoices/{$invoice->id}/emails", ['attach_pdf' => false])->json('data.id');

    $this->postJson("/api/invoices/{$invoice->id}/emails/{$id}/attachments/import", ['source' => 'invoice_pdf'])
        ->assertCreated()
        ->assertJsonCount(1, 'data.attachments');
    $this->postJson("/api/invoices/{$invoice->id}/emails/{$id}/attachments/import", ['source' => 'quote_pdf'])
        ->assertUnprocessable();
});

it('AC-004: 403 without invoices.sendEmail (write) and without invoices.viewEmails (index)', function () {
    $invoice = invoiceWithCustomerContacts();
    Sanctum::actingAs(invoiceEmailUser(['view', 'viewEmails']));

    $this->postJson("/api/invoices/{$invoice->id}/emails", [])->assertForbidden();
    $this->postJson("/api/invoices/{$invoice->id}/emails/reminder")->assertForbidden();
    $this->getJson("/api/invoices/{$invoice->id}/emails")->assertOk();

    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail']));

    $this->getJson("/api/invoices/{$invoice->id}/emails")->assertForbidden();
});

it('AC-004: GET invoice exposes view_emails and send_email', function () {
    $invoice = invoiceWithCustomerContacts();
    Sanctum::actingAs(invoiceEmailUser(['view', 'viewEmails']));

    $this->getJson("/api/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_emails', true)
        ->assertJsonPath('permissions.actions.send_email', false);
});

it('AC-005: reminder on an overdue invoice creates a reminder draft with the overdue list, amount and the PDF', function () {
    invoicesDefaultLayout();
    $invoice = overdueInvoice();
    $template = EmailTemplate::factory()->create([
        'module' => EmailTemplateModule::Invoices,
        'subject' => 'Reminder {invoice.number_label}',
        'body' => '<p>Overdue {reminder.overdue_amount}</p>{reminder.overdue_installments}',
    ]);
    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail']));

    $response = $this->postJson("/api/invoices/{$invoice->id}/emails/reminder", ['email_template_id' => $template->id])
        ->assertCreated()
        ->assertJsonPath('data.purpose', 'reminder')
        ->assertJsonPath('data.to', ['customer@pec.test'])
        ->assertJsonCount(1, 'data.attachments');

    expect($response->json('data.subject'))->toBe('Reminder 5/2026')
        ->and($response->json('data.body'))->toContain('<ul><li>')->toContain('100,00')->toContain('Overdue 100,00')
        ->and($response->json('data.body'))->not->toContain('50,00');
});

it('AC-005: reminder without a template gives an empty draft; wrong-module template is 422', function () {
    invoicesDefaultLayout();
    $invoice = overdueInvoice();
    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail']));

    $this->postJson("/api/invoices/{$invoice->id}/emails/reminder")->assertCreated()->assertJsonPath('data.subject', null);

    $other = EmailTemplate::factory()->create();

    $this->postJson("/api/invoices/{$invoice->id}/emails/reminder", ['email_template_id' => $other->id])->assertUnprocessable();
});

it('AC-005: reminder on an invoice without overdue installments is a 409', function () {
    $invoice = invoiceWithCustomerContacts();
    InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'due_date' => Carbon::today()->addDays(10)->toDateString()]);
    Sanctum::actingAs(invoiceEmailUser(['view', 'sendEmail']));

    $this->postJson("/api/invoices/{$invoice->id}/emails/reminder")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This invoice has no overdue installments.');
});

it('AC-005: the invoices grid exposes last_reminder_at after a reminder is sent, null otherwise', function () {
    $invoice = overdueInvoice();
    $other = Invoice::factory()->create();
    OutboundEmail::factory()->sent()->forEmailable($invoice)->create(['purpose' => OutboundEmailPurpose::Reminder, 'sent_at' => '2026-09-20 10:00:00']);
    OutboundEmail::factory()->sent()->forEmailable($other)->create(['purpose' => OutboundEmailPurpose::Document]);
    OutboundEmail::factory()->forEmailable($other)->create(['purpose' => OutboundEmailPurpose::Reminder]);
    $user = invoiceEmailUser(['viewAny', 'view', 'sendEmail']);
    Sanctum::actingAs($user);

    $rows = collect($this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$invoice->id]['last_reminder_at'])->toBe('2026-09-20')
        ->and($rows[$other->id]['last_reminder_at'])->toBeNull()
        ->and($rows[$invoice->id]['actions'])->toContain('pdf', 'email', 'remind');

    $filtered = $this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 50, 'filterModel' => [
        'last_reminder_at' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-09-01'],
    ]])->assertOk()->json('items');
    $sorted = $this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 50, 'sortModel' => [['colId' => 'last_reminder_at', 'sort' => 'desc']]])->assertOk();

    expect(collect($filtered)->pluck('id')->all())->toBe([$invoice->id]);
    $sorted->assertOk();
});

it('invoice email variable catalogue lists the invoice categories and the reminder variables', function () {
    Permission::findOrCreate('email-templates.view');
    $user = User::factory()->create();
    $user->givePermissionTo('email-templates.view');
    Sanctum::actingAs($user);

    $keys = collect($this->getJson('/api/email-templates/variables?module=invoices')->assertOk()->json('data'))->pluck('key')->all();

    expect($keys)->toBe(['invoice', 'customer', 'company', 'payment', 'totals', 'sender', 'reminder']);
});

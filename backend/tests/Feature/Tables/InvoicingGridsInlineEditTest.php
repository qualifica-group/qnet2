<?php

declare(strict_types=1);

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0260: the invoicing grids edit their cells through each module's own
// update FormRequest + service.

uses(RefreshDatabase::class);

function invoicingCellPatch(string $resource, int $id, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/{$resource}/rows/{$id}", ['column' => $column, 'value' => $value]);
}

/**
 * @return array<int, string>
 */
function invoicingEditableColumns(string $resource): array
{
    return collect(test()->getJson("/api/tables/{$resource}/columns")->assertOk()->json('data.columns'))
        ->where('editable', true)->pluck('id')->sort()->values()->all();
}

function invoiceTagGridEditor(): User
{
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("invoice-tags.{$ability}", 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo(['invoice-tags.viewAny', 'invoice-tags.view', 'invoice-tags.update']);

    return $user;
}

it('AC-001: edits the invoice tag cells through the form rules', function () {
    Sanctum::actingAs(invoiceTagGridEditor());
    $tag = InvoiceTag::factory()->create(['name' => 'Acconto']);

    expect(invoicingEditableColumns('invoice-tags'))->toBe(['color', 'description', 'is_active', 'name']);

    invoicingCellPatch('invoice-tags', $tag->id, 'name', 'Saldo')->assertOk()->assertJsonPath('data.name', 'Saldo');
    invoicingCellPatch('invoice-tags', $tag->id, 'color', 'teal')->assertOk()->assertJsonPath('data.color', 'teal');
    invoicingCellPatch('invoice-tags', $tag->id, 'is_active', false)->assertOk()->assertJsonPath('data.is_active', false);
    invoicingCellPatch('invoice-tags', $tag->id, 'name', 'Preventivo')->assertUnprocessable();
    invoicingCellPatch('invoice-tags', $tag->id, 'color', '#ff0000')->assertUnprocessable();
});

it('AC-002: edits due date and payment method of an unpaid installment', function () {
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view', 'update']));
    $invoice = installmentInvoice(attributes: ['document_date' => '2026-03-15']);
    $installment = installmentOf($invoice, ['due_date' => '2026-04-15', 'collected_amount' => '0.00']);

    expect(invoicingEditableColumns('invoice-installments'))->toBe(['due_date', 'payment_method_code']);

    invoicingCellPatch('invoice-installments', $installment->id, 'due_date', '2026-05-20')->assertOk()->assertJsonPath('data.due_date', '2026-05-20');
    invoicingCellPatch('invoice-installments', $installment->id, 'due_date', '2026-01-01')->assertUnprocessable();

    $row = collect($this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->first();
    expect($row['actions'])->not->toContain('edit');
});

it('AC-002: a collected installment is a read-only row', function () {
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view', 'update']));
    $invoice = installmentInvoice(attributes: ['document_date' => '2026-03-15']);
    $installment = installmentOf($invoice, ['due_date' => '2026-04-15', 'amount' => '100.00', 'collected_amount' => '40.00', 'collected_at' => '2026-04-10']);

    $row = collect($this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->first();
    expect($row['editable'])->toBeFalse();

    invoicingCellPatch('invoice-installments', $installment->id, 'due_date', '2026-05-20')->assertForbidden();
});

it('AC-003: edits tag and deviation of a registered invoice', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view', 'update']));
    $invoice = Invoice::factory()->create(['type' => InvoiceType::Invoice, 'external_number' => '12', 'external_date' => '2026-03-20']);
    $tag = referenceInvoiceTag('Consuntivo');

    expect(invoicingEditableColumns('invoices'))->toBe(['deviation', 'tag']);

    invoicingCellPatch('invoices', $invoice->id, 'tag', $tag->id)->assertOk()->assertJsonPath('data.tag.id', $tag->id);
    invoicingCellPatch('invoices', $invoice->id, 'deviation', 12.5)->assertOk();
    invoicingCellPatch('invoices', $invoice->id, 'tag', null)->assertOk()->assertJsonPath('data.tag', null);

    expect((float) $invoice->fresh()->deviation)->toBe(12.5);
});

it('AC-003: forbids the invoice cell edit without update', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view']));
    $invoice = Invoice::factory()->create();

    invoicingCellPatch('invoices', $invoice->id, 'deviation', 1)->assertForbidden();
});

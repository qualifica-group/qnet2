<?php

use App\Models\Invoice;
use App\Models\InvoiceTag;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\Invoices\InvoiceRenderSubject;
use App\Services\DocumentLayouts\Rendering\Invoices\InvoiceSampleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// spec 0247: the tag on invoices (AC-008) and the tag_label layout variable (AC-009).

function invoiceTagUserWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'update', 'downgrade'] as $ability) {
        Permission::findOrCreate("invoices.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("invoices.{$ability}");
    }

    return $user;
}

it('AC-008: the details accept invoice_tag_id, expose tag {id,name,color} and accept null to clear it', function () {
    Sanctum::actingAs(invoiceTagUserWith(['view', 'update']));
    $invoice = Invoice::factory()->create();
    $tag = referenceInvoiceTag('Preventivo');

    $this->patchJson("/api/invoices/{$invoice->id}/details", ['invoice_tag_id' => $tag->id])
        ->assertOk()
        ->assertJsonPath('data.tag', ['id' => $tag->id, 'name' => 'Preventivo', 'color' => 'amber']);
    $this->getJson("/api/invoices/{$invoice->id}")->assertOk()->assertJsonPath('data.tag.id', $tag->id);

    $this->patchJson("/api/invoices/{$invoice->id}/details", ['invoice_tag_id' => null])->assertOk()->assertJsonPath('data.tag', null);
    expect($invoice->fresh()->invoice_tag_id)->toBeNull();
});

it('AC-008: an unknown or inactive tag is 422, an inactive tag already assigned stays valid', function () {
    Sanctum::actingAs(invoiceTagUserWith(['view', 'update']));
    $inactive = InvoiceTag::factory()->create(['is_active' => false]);
    $invoice = Invoice::factory()->create();
    $carrier = Invoice::factory()->create(['invoice_tag_id' => $inactive->id]);

    $this->patchJson("/api/invoices/{$invoice->id}/details", ['invoice_tag_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('invoice_tag_id');
    $this->patchJson("/api/invoices/{$invoice->id}/details", ['invoice_tag_id' => $inactive->id])->assertUnprocessable()->assertJsonValidationErrors('invoice_tag_id');
    $this->patchJson("/api/invoices/{$invoice->id}/details", ['invoice_tag_id' => 'final'])->assertUnprocessable()->assertJsonValidationErrors('invoice_tag_id');
    $this->patchJson("/api/invoices/{$carrier->id}/details", ['invoice_tag_id' => $inactive->id])->assertOk()->assertJsonPath('data.tag.id', $inactive->id);
});

it('AC-008: the tag stays editable on a registered invoice and the details need invoices.update', function () {
    $invoice = Invoice::factory()->asInvoice()->create();
    $tag = referenceInvoiceTag('Consuntivo');

    Sanctum::actingAs(invoiceTagUserWith(['view']));
    $this->patchJson("/api/invoices/{$invoice->id}/details", ['invoice_tag_id' => $tag->id])->assertForbidden();

    Sanctum::actingAs(invoiceTagUserWith(['view', 'update']));
    $this->patchJson("/api/invoices/{$invoice->id}/details", ['invoice_tag_id' => $tag->id])
        ->assertOk()->assertJsonPath('data.type', 'invoice')->assertJsonPath('data.tag.name', 'Consuntivo');
});

it('AC-008: the grid row carries the tag badge and the set filter narrows by tag name', function () {
    Sanctum::actingAs(invoiceTagUserWith(['viewAny']));
    $estimate = Invoice::factory()->create(['invoice_tag_id' => referenceInvoiceTag('Preventivo')->id]);
    $final = Invoice::factory()->create(['invoice_tag_id' => referenceInvoiceTag('Consuntivo')->id]);
    $untagged = Invoice::factory()->create();

    $all = collect($this->postJson('/api/tables/invoices/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))->keyBy('id');
    expect($all[$final->id]['tag'])->toBe(['id' => $final->invoice_tag_id, 'name' => 'Consuntivo', 'color' => 'teal'])
        ->and($all[$untagged->id]['tag'])->toBeNull();

    $filtered = $this->postJson('/api/tables/invoices/rows', [
        'startRow' => 0, 'endRow' => 50,
        'filterModel' => ['tag' => ['filterType' => 'set', 'values' => ['Preventivo']]],
    ])->assertOk()->json('items');
    expect(collect($filtered)->pluck('id')->all())->toBe([$estimate->id]);

    $values = $this->postJson('/api/tables/invoices/values', ['columnId' => 'tag'])->assertOk()->json('data.values');
    expect($values)->toBe(['Consuntivo', 'Preventivo']);
});

it('AC-009: the tag_label layout variable prints the tag name, empty without a tag, and the sample has one', function () {
    $actor = User::factory()->create();
    $tagged = Invoice::factory()->create(['invoice_tag_id' => InvoiceTag::factory()->create(['name' => 'Saldo finale'])->id]);
    $untagged = Invoice::factory()->create();

    $subject = InvoiceRenderSubject::for($tagged);
    $subject->prepare();
    $empty = InvoiceRenderSubject::for($untagged);
    $empty->prepare();
    $sample = InvoiceRenderSubject::for(app(InvoiceSampleFactory::class)->make());
    $sample->prepare();

    expect($subject->resolveVariable('invoice', 'tag_label', $actor))->toBe('Saldo finale')
        ->and($empty->resolveVariable('invoice', 'tag_label', $actor))->toBe('')
        ->and($sample->resolveVariable('invoice', 'tag_label', $actor))->not->toBe('');
});

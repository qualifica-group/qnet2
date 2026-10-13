<?php

use App\Models\Attachment;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

if (! function_exists('invoiceAttachmentActor')) {
    /**
     * A user holding exactly the given attachments abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function invoiceAttachmentActor(array $abilities): User
    {
        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("attachments.{$ability}", 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

it('AC-010: uploads, lists and deletes the supplier document of a payable invoice', function () {
    Sanctum::actingAs(invoiceAttachmentActor(['viewAny', 'create', 'delete']));
    $document = purchaseDocument();

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('fattura.pdf', 16, 'application/pdf'),
        'attachable_type' => 'purchase_invoice',
        'attachable_id' => $document->id,
        'collection' => 'documents',
    ])->assertCreated()
        ->assertJsonPath('data.attachable_type', 'purchase_invoice')
        ->assertJsonPath('data.attachable_id', $document->id);

    $attachment = Attachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $listed = $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'purchase_invoice',
        'attachable_id' => $document->id,
        'collection' => 'documents',
    ]))->assertOk()->json('data');

    expect(array_column($listed, 'id'))->toBe([$attachment->id]);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();

    expect(Attachment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

it('AC-010: refuses the upload without attachments.create and an unknown owner', function () {
    $document = purchaseDocument();
    $payload = fn (int $id): array => [
        'file' => UploadedFile::fake()->create('fattura.pdf', 16, 'application/pdf'),
        'attachable_type' => 'purchase_invoice',
        'attachable_id' => $id,
        'collection' => 'documents',
    ];

    Sanctum::actingAs(invoiceAttachmentActor(['viewAny']));
    $this->postJson('/api/attachments', $payload($document->id))->assertForbidden();

    Sanctum::actingAs(invoiceAttachmentActor(['create']));
    $this->postJson('/api/attachments', $payload($document->id + 999))->assertUnprocessable();
});

it('AC-010: deleting the proforma removes its attachments and stored files', function () {
    Sanctum::actingAs(invoiceAttachmentActor(['create']));
    $document = purchaseDocument(null, ['document_type' => 'proforma', 'document_number' => null]);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('proforma.pdf', 16, 'application/pdf'),
        'attachable_type' => 'purchase_invoice',
        'attachable_id' => $document->id,
        'collection' => 'documents',
    ])->assertCreated();
    $path = Attachment::query()->firstOrFail()->path;

    $document->delete();

    expect(PurchaseInvoice::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

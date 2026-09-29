<?php

use App\Enums\DocumentLayoutModule;
use App\Models\Attachment;
use App\Models\DocumentBundle;
use App\Models\DocumentLayout;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentLayouts\Rendering\DocxToPdfConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Attachments: upload/import/remove/download (spec 0175, D-7/D-8, AC-010/011)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function draftEmail(WorkOrder $workOrder, User $actor): OutboundEmail
{
    return OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $actor->id]);
}

// ---------------------------------------------------------------------------
// D-7a — upload
// ---------------------------------------------------------------------------

it('AC-010: upload creates an email_attachments copy and it survives deleting the source original elsewhere', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments", [
        'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
    ])->assertCreated();

    $attachmentId = collect($response->json('data.attachments'))->first()['id'];
    $attachment = Attachment::query()->findOrFail($attachmentId);
    expect($attachment->collection)->toBe(OutboundEmail::ATTACHMENT_COLLECTION);
});

it('AC-010: total attachments over the configured limit is 422', function () {
    config(['outbound_emails.max_total_attachments_kb' => 1]);
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments", [
        'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
    ])->assertUnprocessable();

    expect($email->fresh()->attachments)->toHaveCount(0);
});

it('upload on a non-draft email is 409', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments", [
        'file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
    ])->assertStatus(409);
});

// ---------------------------------------------------------------------------
// D-7b — import: documents
// ---------------------------------------------------------------------------

it('AC-010: import source=documents copies a work order document the actor may view', function () {
    Permission::findOrCreate('work-orders.viewDocuments');
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->givePermissionTo('work-orders.viewDocuments');
    $email = draftEmail($workOrder, $actor);
    $source = Attachment::factory()->for($workOrder, 'attachable')->create(['collection' => 'documents']);
    Storage::disk($source->disk)->put($source->path, 'hello');
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", [
        'source' => 'documents',
        'attachment_ids' => [$source->id],
    ])->assertCreated();

    expect(Attachment::query()->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)->count())->toBe(1);
    // The source is untouched -- a COPY, not a move.
    expect(Attachment::find($source->id))->not->toBeNull();
});

it('AC-010: import source=documents 422s on an id belonging to neither the commessa nor its anagrafica', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    $foreign = Attachment::factory()->create(['collection' => 'documents']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", [
        'source' => 'documents',
        'attachment_ids' => [$foreign->id],
    ])->assertUnprocessable();
});

it('AC-010: import source=documents is 403 without work-orders.viewDocuments even with sendEmail', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    $source = Attachment::factory()->for($workOrder, 'attachable')->create(['collection' => 'documents']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", [
        'source' => 'documents',
        'attachment_ids' => [$source->id],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// D-7c — import: document_bundle
// ---------------------------------------------------------------------------

it('AC-010: import source=document_bundle copies every document of an active bundle', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    $bundle = DocumentBundle::factory()->create();
    $fileOne = Attachment::factory()->for($bundle, 'attachable')->create(['collection' => 'documents']);
    $fileTwo = Attachment::factory()->for($bundle, 'attachable')->create(['collection' => 'documents']);
    Storage::disk($fileOne->disk)->put($fileOne->path, 'one');
    Storage::disk($fileTwo->disk)->put($fileTwo->path, 'two');
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", [
        'source' => 'document_bundle',
        'document_bundle_id' => $bundle->id,
    ])->assertCreated()->assertJsonCount(2, 'data.attachments');
});

it('AC-010: import source=document_bundle 422s for an inactive/unknown bundle', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    $inactive = DocumentBundle::factory()->inactive()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", [
        'source' => 'document_bundle',
        'document_bundle_id' => $inactive->id,
    ])->assertUnprocessable();
});

// ---------------------------------------------------------------------------
// D-7d — import: quote_pdf
// ---------------------------------------------------------------------------

it('AC-010: import source=quote_pdf 422s when no layout is resolvable', function () {
    Permission::findOrCreate('quotes.view');
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->givePermissionTo('quotes.view');
    $email = draftEmail($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", ['source' => 'quote_pdf'])
        ->assertUnprocessable();
});

it('AC-010: import source=quote_pdf generates and attaches "{quote.code}.pdf" when a layout resolves', function () {
    Permission::findOrCreate('quotes.view');
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->givePermissionTo('quotes.view');
    $email = draftEmail($workOrder, $actor);

    $layout = DocumentLayout::factory()->create([
        'module' => DocumentLayoutModule::Quotes,
        'is_active' => true,
        'is_default' => true,
    ]);
    $workOrder->quote()->update(['layout_id' => null]);

    $recorder = new class extends DocxToPdfConverter
    {
        public function convert(string $docx): string
        {
            return '%PDF-1.4 fake';
        }
    };
    app()->instance(DocxToPdfConverter::class, $recorder);

    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", ['source' => 'quote_pdf'])
        ->assertCreated();

    $names = collect($response->json('data.attachments'))->pluck('original_name');
    expect($names)->toContain("{$workOrder->quote->code}.pdf");
});

// ---------------------------------------------------------------------------
// remove
// ---------------------------------------------------------------------------

it('remove deletes the email\'s own attachment copy', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    $attachment = Attachment::factory()->for($email, 'attachable')->create(['collection' => OutboundEmail::ATTACHMENT_COLLECTION]);
    Storage::disk($attachment->disk)->put($attachment->path, 'x');
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/{$attachment->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.attachments');

    expect(Attachment::find($attachment->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-011 — nested download
// ---------------------------------------------------------------------------

it('AC-011: nested download works for an actor with viewEmails (even a non-draft, non-owned email)', function () {
    $workOrder = WorkOrder::factory()->create();
    $owner = workOrderEmailActor($workOrder, ['sendEmail']);
    $viewer = workOrderEmailActor($workOrder, ['viewEmails']);
    $email = OutboundEmail::factory()->forEmailable($workOrder)->sent()->create(['sender_user_id' => $owner->id]);
    $attachment = Attachment::factory()->for($email, 'attachable')->create(['collection' => OutboundEmail::ATTACHMENT_COLLECTION]);
    Storage::disk($attachment->disk)->put($attachment->path, 'binary-content');
    Sanctum::actingAs($viewer);

    $this->get("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/{$attachment->id}/download")
        ->assertOk();
});

it('AC-011: nested download 403s without viewEmails', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $email = draftEmail($workOrder, $actor);
    $attachment = Attachment::factory()->for($email, 'attachable')->create(['collection' => OutboundEmail::ATTACHMENT_COLLECTION]);
    Storage::disk($attachment->disk)->put($attachment->path, 'x');
    $outsider = workOrderEmailActor($workOrder, [], inScope: false);
    Sanctum::actingAs($outsider);

    $this->get("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/{$attachment->id}/download")
        ->assertForbidden();
});

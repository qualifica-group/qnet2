<?php

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0415: Gestione Richieste/Iscritti documents live on the OFFER. The
 * `quote` owner is owner-scoped (D-1) and reached through QuoteDocumentAccess
 * (D-2): quote view, request module perimeter, or a visible contract.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("attachments.{$ability}");
    }

    foreach (['quotes', 'contracts', 'request-management', 'enrollee-management'] as $resource) {
        foreach (['viewAny', 'view', 'viewAll'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }
    }
});

/**
 * @param  array<int, string>  $permissions
 */
function quoteDocumentsActor(array $permissions): User
{
    $actor = User::factory()->create();
    $actor->givePermissionTo(['attachments.viewAny', 'attachments.view', 'attachments.create', 'attachments.delete', ...$permissions]);

    return $actor;
}

function quoteDocumentUpload(int $quoteId): array
{
    return [
        'file' => UploadedFile::fake()->create('offer.pdf', 8, 'application/pdf'),
        'attachable_type' => 'quote',
        'attachable_id' => $quoteId,
        'collection' => 'documents',
    ];
}

it('request management operator lists, uploads, downloads and deletes the documents of an offer in scope (AC-001)', function () {
    $actor = quoteDocumentsActor(['request-management.view']);
    $quote = Quote::factory()->create(['operator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', quoteDocumentUpload($quote->id))
        ->assertCreated()
        ->assertJsonPath('data.attachable_type', 'quote')
        ->assertJsonPath('data.attachable_id', $quote->id);

    $attachment = Attachment::query()->firstOrFail();

    $this->getJson("/api/attachments?attachable_type=quote&attachable_id={$quote->id}&collection=documents")
        ->assertOk()->assertJsonCount(1, 'data');
    $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();

    expect($quote->attachments()->count())->toBe(0);
});

it('request management without scope on the offer is refused on every endpoint (AC-001)', function () {
    $actor = quoteDocumentsActor(['request-management.view']);
    $quote = Quote::factory()->create(['operator_id' => User::factory()->create()->id]);
    $attachment = Attachment::factory()->create(['attachable_type' => 'quote', 'attachable_id' => $quote->id, 'disk' => 'local']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments?attachable_type=quote&attachable_id={$quote->id}")->assertForbidden();
    $this->postJson('/api/attachments', quoteDocumentUpload($quote->id))->assertForbidden();
    $this->getJson("/api/attachments/{$attachment->id}")->assertForbidden();
    $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();

    expect(Attachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
});

it('request management viewAll reaches every offer documents (AC-001)', function () {
    $actor = quoteDocumentsActor(['request-management.view', 'request-management.viewAll']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments?attachable_type=quote&attachable_id={$quote->id}")->assertOk();
});

it('a quotes viewer of the offer reaches its documents, of another offer does not (AC-002)', function () {
    $actor = quoteDocumentsActor(['quotes.view']);
    $mine = Quote::factory()->create(['supervisor_id' => $actor->id]);
    $other = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments?attachable_type=quote&attachable_id={$mine->id}")->assertOk();
    $this->getJson("/api/attachments?attachable_type=quote&attachable_id={$other->id}")->assertForbidden();
});

it('a viewer of a contract on the offer reaches its documents, another contract viewer does not (AC-002)', function () {
    $actor = quoteDocumentsActor(['contracts.view']);
    $mine = Quote::factory()->create(['supervisor_id' => $actor->id]);
    Contract::factory()->create(['quote_id' => $mine->id]);
    $other = Quote::factory()->create();
    Contract::factory()->create(['quote_id' => $other->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments?attachable_type=quote&attachable_id={$mine->id}")->assertOk();
    $this->getJson("/api/attachments?attachable_type=quote&attachable_id={$other->id}")->assertForbidden();
});

it('the request management row counts the offer documents, not the opportunity ones (AC-003)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    $first = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $sister = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Attachment::factory()->count(2)->create(['attachable_type' => 'quote', 'attachable_id' => $first->id, 'collection' => 'documents']);
    Attachment::factory()->create(['attachable_type' => 'quote', 'attachable_id' => $first->id, 'collection' => 'other']);
    Attachment::factory()->create(['attachable_type' => 'opportunity', 'attachable_id' => $opportunity->id, 'collection' => 'documents']);
    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('id');

    expect($rows[$first->id]['documents_count'])->toBe(2)
        ->and($rows[$sister->id]['documents_count'])->toBe(0);
});

it('deleting an offer removes its documents (AC-004)', function () {
    $quote = Quote::factory()->create();
    $attachment = Attachment::factory()->create(['attachable_type' => 'quote', 'attachable_id' => $quote->id, 'disk' => 'local']);
    Storage::disk('local')->put($attachment->path, 'x');

    $quote->delete();

    expect(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($attachment->path);
});

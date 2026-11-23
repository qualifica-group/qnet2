<?php

use App\Jobs\SyncDocumentChecklistJob;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
| Document checklists CRUD (spec 0403): AC-001..AC-006, AC-013, AC-014.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('AC-001: store creates the checklist with uppercase code, positioned items, pending, one job', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create', 'view']));

    $response = $this->postJson('/api/document-checklists', documentChecklistPayload())
        ->assertCreated()
        ->assertJsonPath('data.code', 'APERTURA')
        ->assertJsonPath('data.sync_status', 'pending')
        ->assertJsonPath('data.code_locked', false)
        ->assertJsonPath('data.items.0.name', 'Documento A')
        ->assertJsonPath('data.items.1.name', 'Documento B');

    $checklist = DocumentChecklist::findOrFail($response->json('data.id'));
    expect($checklist->items()->pluck('position')->all())->toBe([0, 1]);

    Queue::assertPushed(SyncDocumentChecklistJob::class, 1);
    Queue::assertPushed(SyncDocumentChecklistJob::class, fn ($job) => $job->documentChecklistId === $checklist->id);
});

it('AC-002: store rejects every invalid field, creates nothing and queues nothing', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create']));

    $items = array_fill(0, 201, documentChecklistItemPayload());
    $items[0] = documentChecklistItemPayload(['allowed_file_types' => []]);
    $items[1] = documentChecklistItemPayload(['min_files' => 3, 'max_files' => 2]);
    $items[2] = documentChecklistItemPayload(['max_file_size_kb' => 20481]);
    $items[3] = documentChecklistItemPayload(['max_age_days' => 3651]);
    $items[4] = documentChecklistItemPayload(['allowed_file_types' => ['exe']]);
    $items[5] = documentChecklistItemPayload(['allowed_file_types' => ['pdf', 'pdf']]);

    $this->postJson('/api/document-checklists', documentChecklistPayload([
        'code' => 'ab c',
        'name' => str_repeat('a', 256),
        'items' => $items,
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'code', 'name', 'items',
        'items.0.allowed_file_types',
        'items.1.max_files',
        'items.2.max_file_size_kb',
        'items.3.max_age_days',
        'items.4.allowed_file_types.0',
        'items.5.allowed_file_types',
    ]);

    expect(DocumentChecklist::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('store: an item id is refused on creation', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create']));

    $this->postJson('/api/document-checklists', documentChecklistPayload([
        'items' => [documentChecklistItemPayload(['id' => 1])],
    ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
});

it('AC-003: the code is unique among non-deleted checklists only', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create']));
    $existing = DocumentChecklist::factory()->create(['code' => 'APERTURA']);

    $this->postJson('/api/document-checklists', documentChecklistPayload(['code' => 'apertura']))
        ->assertUnprocessable()->assertJsonValidationErrors('code');

    $existing->delete();

    $this->postJson('/api/document-checklists', documentChecklistPayload(['code' => 'apertura']))->assertCreated();
});

it('show: returns the checklist with its permissions block', function () {
    Sanctum::actingAs(documentChecklistUserWith(['view', 'update']));
    $checklist = documentChecklistWithItems(['A', 'B']);

    $this->getJson("/api/document-checklists/{$checklist->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $checklist->id)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('permissions.fields.code.editable', true);
});

it('AC-004: update replaces the items: match by id, delete the missing, position = index', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = documentChecklistWithItems(['A', 'B', 'C']);
    [$a, $b, $c] = $checklist->items()->get()->all();

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload([
        'code' => $checklist->code,
        'items' => [
            documentChecklistItemPayload(['id' => $c->id, 'name' => 'C']),
            documentChecklistItemPayload(['name' => 'D']),
            documentChecklistItemPayload(['id' => $a->id, 'name' => 'A']),
        ],
    ]))->assertOk()->assertJsonPath('data.sync_status', 'pending');

    expect(DocumentChecklistItem::find($b->id))->toBeNull()
        ->and($c->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(2)
        ->and($checklist->items()->where('name', 'D')->value('position'))->toBe(1);
    Queue::assertPushed(SyncDocumentChecklistJob::class, 1);
});

it('AC-005: an item id of another checklist is refused and nothing changes', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = documentChecklistWithItems(['A']);
    $foreign = documentChecklistWithItems(['X'])->items()->firstOrFail();

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload([
        'name' => 'Changed',
        'code' => $checklist->code,
        'items' => [documentChecklistItemPayload(['id' => $foreign->id])],
    ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.id');

    expect($checklist->fresh()->name)->not->toBe('Changed')
        ->and($checklist->items()->count())->toBe(1)
        ->and($foreign->fresh()->name)->toBe('X');
    Queue::assertNothingPushed();
});

it('AC-006: a code-locked checklist refuses a code change but accepts the same code', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update', 'view']));
    $checklist = DocumentChecklist::factory()->create(['code' => 'APERTURA', 'first_synced_at' => now()]);

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload(['code' => 'CHIUSURA', 'items' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors('code');

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload(['code' => 'apertura', 'items' => []]))
        ->assertOk()->assertJsonPath('data.code', 'APERTURA');

    $this->getJson("/api/document-checklists/{$checklist->id}")->assertJsonPath('data.code_locked', true);
});

it('update: a never-synced checklist can change its code', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = DocumentChecklist::factory()->create(['code' => 'APERTURA']);

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload(['code' => 'chiusura', 'items' => []]))
        ->assertOk()->assertJsonPath('data.code', 'CHIUSURA');
});

it('update: 404 on a deleted checklist', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = DocumentChecklist::factory()->create();
    $checklist->delete();

    $this->putJson("/api/document-checklists/{$checklist->id}", documentChecklistPayload())->assertNotFound();
});

it('AC-013: destroy soft deletes, answers 404 afterwards and queues the remote delete', function () {
    Sanctum::actingAs(documentChecklistUserWith(['delete', 'view']));
    $checklist = DocumentChecklist::factory()->create();

    $this->deleteJson("/api/document-checklists/{$checklist->id}")->assertNoContent();

    $this->assertSoftDeleted('document_checklists', ['id' => $checklist->id]);
    $this->getJson("/api/document-checklists/{$checklist->id}")->assertNotFound();
    $this->deleteJson("/api/document-checklists/{$checklist->id}")->assertNotFound();
    Queue::assertPushed(SyncDocumentChecklistJob::class, 1);
});

it('AC-014: activation toggles is_active, goes pending and queues the job', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = DocumentChecklist::factory()->create(['sync_status' => 'synced']);

    $this->patchJson("/api/document-checklists/{$checklist->id}/activation", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.sync_status', 'pending');

    Queue::assertPushed(SyncDocumentChecklistJob::class, 1);
});

it('AC-014: activation requires a boolean is_active', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = DocumentChecklist::factory()->create();

    $this->patchJson("/api/document-checklists/{$checklist->id}/activation", [])
        ->assertUnprocessable()->assertJsonValidationErrors('is_active');
    $this->patchJson("/api/document-checklists/{$checklist->id}/activation", ['is_active' => 'maybe'])
        ->assertUnprocessable()->assertJsonValidationErrors('is_active');
    Queue::assertNothingPushed();
});

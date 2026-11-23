<?php

use App\Enums\DocumentChecklistSyncOperation;
use App\Enums\DocumentChecklistSyncOutcome;
use App\Jobs\SyncDocumentChecklistJob;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistSyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
| Manual sync and sync log endpoints (spec 0403): AC-015, AC-020.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('AC-015: sync-all queues one job per non-deleted checklist and returns the count', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    DocumentChecklist::factory()->count(2)->create(['sync_status' => 'synced']);
    DocumentChecklist::factory()->inactive()->create(['sync_status' => 'failed']);
    DocumentChecklist::factory()->create()->delete();

    $this->postJson('/api/document-checklists/sync-all')
        ->assertStatus(202)
        ->assertJsonPath('data.queued', 3);

    Queue::assertPushed(SyncDocumentChecklistJob::class, 3);
    expect(DocumentChecklist::where('sync_status', 'pending')->count())->toBe(3);
});

it('AC-015: sync queues one job and sets the checklist to pending', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = DocumentChecklist::factory()->create(['sync_status' => 'failed', 'sync_error' => 'boom']);

    $this->postJson("/api/document-checklists/{$checklist->id}/sync")
        ->assertStatus(202)
        ->assertJsonPath('data.sync_status', 'pending')
        ->assertJsonPath('data.sync_error', null);

    Queue::assertPushed(SyncDocumentChecklistJob::class, 1);
});

it('sync: 404 on a deleted checklist', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $checklist = DocumentChecklist::factory()->create();
    $checklist->delete();

    $this->postJson("/api/document-checklists/{$checklist->id}/sync")->assertNotFound();
    Queue::assertNothingPushed();
});

it('AC-020: sync-logs returns at most 20 rows of the requested checklist, newest first', function () {
    Sanctum::actingAs(documentChecklistUserWith(['view']));
    $checklist = DocumentChecklist::factory()->create();
    $other = DocumentChecklist::factory()->create();

    foreach (range(1, 25) as $minute) {
        DocumentChecklistSyncLog::create([
            'document_checklist_id' => $checklist->id,
            'operation' => DocumentChecklistSyncOperation::Upsert,
            'outcome' => DocumentChecklistSyncOutcome::Success,
            'http_status' => 200,
            'attempt' => 1,
            'message' => "call {$minute}",
            'created_at' => now()->subHours(30)->addMinutes($minute),
        ]);
    }
    DocumentChecklistSyncLog::create([
        'document_checklist_id' => $other->id,
        'operation' => DocumentChecklistSyncOperation::Delete,
        'outcome' => DocumentChecklistSyncOutcome::Failed,
        'http_status' => null,
        'attempt' => 1,
        'message' => 'other checklist',
        'created_at' => now(),
    ]);

    $response = $this->getJson("/api/document-checklists/{$checklist->id}/sync-logs")->assertOk();

    expect($response->json('data'))->toHaveCount(20)
        ->and($response->json('data.0.message'))->toBe('call 25')
        ->and($response->json('data.19.message'))->toBe('call 6')
        ->and(collect($response->json('data'))->pluck('message'))->not->toContain('other checklist')
        ->and($response->json('data.0'))->toHaveKeys(['id', 'operation', 'outcome', 'http_status', 'attempt', 'message', 'created_at'])
        ->and($response->json('data.0.operation'))->toBe('upsert');
});

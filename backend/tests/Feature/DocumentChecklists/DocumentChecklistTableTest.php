<?php

use App\Jobs\SyncDocumentChecklistJob;
use App\Models\DocumentChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
| `document-checklists` grid (spec 0403): AC-013 (bulk delete), AC-021.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('AC-021: rows carry the contract columns, skip deleted checklists and sort by name', function () {
    Sanctum::actingAs(documentChecklistUserWith(['viewAny']));
    documentChecklistWithItems(['A', 'B'], ['name' => 'Zeta', 'code' => 'ZETA', 'sync_status' => 'failed', 'sync_error' => 'Codice in uso']);
    DocumentChecklist::factory()->create(['name' => 'Alfa', 'code' => 'ALFA']);
    DocumentChecklist::factory()->create(['name' => 'Eliminata'])->delete();

    $response = $this->postJson('/api/tables/document-checklists/rows', ['startRow' => 0, 'endRow' => 50])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Alfa', 'Zeta']);

    $zeta = $response->json('items.1');
    expect($zeta)->toHaveKeys(['id', 'name', 'code', 'is_active', 'items_count', 'sync_status', 'sync_error', 'synced_at', 'created_at', 'actions'])
        ->and($zeta['items_count'])->toBe(2)
        ->and($zeta['sync_status'])->toBe('failed')
        ->and($zeta['sync_error'])->toBe('Codice in uso');
});

// REQUIREMENT CHANGED (BE-04): `update` now also offers the `activate` action
// next to `sync` (the grid toggles activation inline); was ['sync'] only.
it('AC-021: the sync and activate actions are offered only with update', function () {
    DocumentChecklist::factory()->create();

    Sanctum::actingAs(documentChecklistUserWith(['viewAny', 'view', 'delete', 'viewActivity']));
    $actions = $this->postJson('/api/tables/document-checklists/rows', ['startRow' => 0, 'endRow' => 10])
        ->assertOk()->json('items.0.actions');
    expect($actions)->toBe(['view', 'delete', 'activity']);

    Sanctum::actingAs(documentChecklistUserWith(['viewAny', 'update']));
    $actions = $this->postJson('/api/tables/document-checklists/rows', ['startRow' => 0, 'endRow' => 10])
        ->assertOk()->json('items.0.actions');
    expect($actions)->toBe(['sync', 'activate']);
});

it('AC-021: filters by sync_status (set) and is_active', function () {
    Sanctum::actingAs(documentChecklistUserWith(['viewAny']));
    DocumentChecklist::factory()->create(['name' => 'Ok', 'sync_status' => 'synced']);
    DocumentChecklist::factory()->inactive()->create(['name' => 'Ko', 'sync_status' => 'failed']);

    $this->postJson('/api/tables/document-checklists/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['sync_status' => ['filterType' => 'set', 'values' => ['failed']]],
    ])->assertOk()->assertJsonPath('items.0.name', 'Ko')->assertJsonCount(1, 'items');
});

it('AC-013: the generic bulk delete takes the same path (soft delete + job)', function () {
    Sanctum::actingAs(documentChecklistUserWith(['viewAny', 'delete']));
    $checklist = DocumentChecklist::factory()->create();

    $this->postJson('/api/tables/document-checklists/bulk-delete', ['ids' => [$checklist->id]])->assertOk();

    $this->assertSoftDeleted('document_checklists', ['id' => $checklist->id]);
    Queue::assertPushed(SyncDocumentChecklistJob::class, 1);

    $rows = $this->postJson('/api/tables/document-checklists/rows', ['startRow' => 0, 'endRow' => 10])->assertOk();
    expect($rows->json('items'))->toBe([]);
});

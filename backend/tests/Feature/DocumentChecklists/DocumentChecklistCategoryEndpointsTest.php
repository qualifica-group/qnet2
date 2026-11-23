<?php

use App\Jobs\SyncDocumentChecklistCategoryJob;
use App\Jobs\SyncDocumentChecklistJob;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistCategory;
use App\Models\DocumentChecklistCategorySyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
| Category endpoints (spec 0410): AC-001, AC-002, AC-003, AC-006, AC-011, AC-012,
| AC-013, AC-015 (sync-logs), AC-016. Permissions are the document-checklists.* ones (D-2).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function categoryPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Mutui', 'description' => 'Pratiche di mutuo', 'sort_order' => 3], $overrides);
}

it('AC-001: creates a category pending and queues the sync once', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create']));

    $response = $this->postJson('/api/document-checklist-categories', categoryPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Mutui')
        ->assertJsonPath('data.sync_status', 'pending')
        ->assertJsonPath('data.checklists_count', 0)
        ->assertJsonPath('permissions.resource.create', true);

    Queue::assertPushed(SyncDocumentChecklistCategoryJob::class, 1);
    Queue::assertPushed(SyncDocumentChecklistCategoryJob::class, fn ($job) => $job->documentChecklistCategoryId === $response->json('data.id'));
    expect(DocumentChecklistCategory::count())->toBe(1)
        ->and(DocumentChecklistCategory::first()->sort_order)->toBe(3);
});

it('AC-001: sort_order defaults to 0 and description to null', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create']));

    $this->postJson('/api/document-checklist-categories', ['name' => 'Solo nome'])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 0)
        ->assertJsonPath('data.description', null);
});

it('AC-002: rejects invalid fields without writing or queuing', function (array $overrides, string $field) {
    Sanctum::actingAs(documentChecklistUserWith(['create']));

    $this->postJson('/api/document-checklist-categories', categoryPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(DocumentChecklistCategory::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'empty name' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
    'description too long' => [['description' => str_repeat('a', 2001)], 'description'],
    'negative sort_order' => [['sort_order' => -1], 'sort_order'],
    'sort_order too high' => [['sort_order' => 65536], 'sort_order'],
]);

it('AC-002: the name is unique case-insensitively among non-deleted categories', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create']));
    $existing = DocumentChecklistCategory::factory()->create(['name' => 'mutui']);

    $this->postJson('/api/document-checklist-categories', categoryPayload(['name' => 'Mutui']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $existing->delete();

    $this->postJson('/api/document-checklist-categories', categoryPayload(['name' => 'Mutui']))->assertCreated();
});

it('AC-002: an update may keep its own name but not take another one', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $category = DocumentChecklistCategory::factory()->create(['name' => 'Mutui']);
    DocumentChecklistCategory::factory()->create(['name' => 'Altra']);

    $this->putJson("/api/document-checklist-categories/{$category->id}", categoryPayload(['name' => 'MUTUI']))->assertOk();
    $this->putJson("/api/document-checklist-categories/{$category->id}", categoryPayload(['name' => 'altra']))
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('AC-003: every endpoint answers 403 without the permission', function (string $method, string $uri) {
    Sanctum::actingAs(documentChecklistUserWith([]));
    $category = DocumentChecklistCategory::factory()->create();

    $this->json($method, str_replace('{id}', (string) $category->id, $uri), categoryPayload(['name' => 'Altro']))->assertForbidden();

    Queue::assertNothingPushed();
})->with([
    'show' => ['GET', '/api/document-checklist-categories/{id}'],
    'store' => ['POST', '/api/document-checklist-categories'],
    'update' => ['PUT', '/api/document-checklist-categories/{id}'],
    'destroy' => ['DELETE', '/api/document-checklist-categories/{id}'],
    'sync' => ['POST', '/api/document-checklist-categories/{id}/sync'],
    'sync-logs' => ['GET', '/api/document-checklist-categories/{id}/sync-logs'],
]);

it('AC-003: the grid answers 403 without viewAny', function () {
    Sanctum::actingAs(documentChecklistUserWith([]));

    $this->postJson('/api/tables/document-checklist-categories/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('AC-003: no permission document-checklist-categories.* exists', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'like', 'document-checklist-categories.%')->count())->toBe(0);
});

it('show returns the category with its checklists_count and permissions, 404 once deleted', function () {
    Sanctum::actingAs(documentChecklistUserWith(['view']));
    $category = DocumentChecklistCategory::factory()->create();
    DocumentChecklist::factory()->count(2)->create(['category_id' => $category->id]);
    DocumentChecklist::factory()->create(['category_id' => $category->id])->delete();

    $this->getJson("/api/document-checklist-categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.checklists_count', 2)
        ->assertJsonPath('permissions.resource.view', true)
        ->assertJsonPath('permissions.resource.update', false);

    $category->delete();
    $this->getJson("/api/document-checklist-categories/{$category->id}")->assertNotFound();
});

it('AC-012: updating a category queues only the category job', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'failed', 'sync_error' => 'boom']);
    DocumentChecklist::factory()->count(2)->create(['category_id' => $category->id, 'sync_status' => 'synced']);

    $this->putJson("/api/document-checklist-categories/{$category->id}", categoryPayload(['name' => 'Rinominata']))
        ->assertOk()
        ->assertJsonPath('data.name', 'Rinominata')
        ->assertJsonPath('data.sync_status', 'pending')
        ->assertJsonPath('data.sync_error', null);

    Queue::assertPushed(SyncDocumentChecklistCategoryJob::class, 1);
    Queue::assertNotPushed(SyncDocumentChecklistJob::class);
    expect(DocumentChecklist::where('sync_status', 'synced')->count())->toBe(2);
});

it('AC-006: refuses to delete a category with non-deleted checklists and says how many', function () {
    Sanctum::actingAs(documentChecklistUserWith(['delete']));
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'synced']);
    DocumentChecklist::factory()->count(2)->create(['category_id' => $category->id]);

    $response = $this->deleteJson("/api/document-checklist-categories/{$category->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category');

    expect($response->json('errors.category.0'))->toContain('2')
        ->and($response->json('errors.category.0'))->toBe(__('document_checklists.category_has_checklists', ['count' => 2]))
        ->and(trans('document_checklists.category_has_checklists', ['count' => 2], 'it'))
        ->toBe("La categoria contiene ancora 2 checklist: spostale in un'altra categoria o eliminale prima.");
    expect($category->fresh()->trashed())->toBeFalse()
        ->and($category->fresh()->sync_status->value)->toBe('synced');
    Queue::assertNothingPushed();
});

it('AC-006: deletes a category with no or only deleted checklists (soft delete + job)', function () {
    Sanctum::actingAs(documentChecklistUserWith(['delete']));
    $empty = DocumentChecklistCategory::factory()->create();
    $withDeleted = DocumentChecklistCategory::factory()->create();
    DocumentChecklist::factory()->create(['category_id' => $withDeleted->id])->delete();

    $this->deleteJson("/api/document-checklist-categories/{$empty->id}")->assertNoContent();
    $this->deleteJson("/api/document-checklist-categories/{$withDeleted->id}")->assertNoContent();

    Queue::assertPushed(SyncDocumentChecklistCategoryJob::class, 2);
    $trashed = DocumentChecklistCategory::onlyTrashed()->find($empty->id);
    expect($trashed)->not->toBeNull()
        ->and($trashed->sync_status->value)->toBe('pending');
    $this->deleteJson("/api/document-checklist-categories/{$empty->id}")->assertNotFound();
});

it('AC-006: the bulk delete of the grid refuses the same way', function () {
    Sanctum::actingAs(documentChecklistUserWith(['viewAny', 'delete']));
    $busy = DocumentChecklistCategory::factory()->create();
    DocumentChecklist::factory()->create(['category_id' => $busy->id]);
    $free = DocumentChecklistCategory::factory()->create();

    $response = $this->postJson('/api/tables/document-checklist-categories/bulk-delete', ['ids' => [$busy->id, $free->id]]);

    $response->assertOk();
    expect($response->json('data.deleted') ?? $response->json('deleted'))->toBe(1)
        ->and(DocumentChecklistCategory::find($busy->id))->not->toBeNull()
        ->and(DocumentChecklistCategory::find($free->id))->toBeNull();
});

it('sync queues one job and sets the category pending; 404 on a deleted one', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $category = DocumentChecklistCategory::factory()->create(['sync_status' => 'failed', 'sync_error' => 'boom']);

    $this->postJson("/api/document-checklist-categories/{$category->id}/sync")
        ->assertStatus(202)
        ->assertJsonPath('data.sync_status', 'pending');
    Queue::assertPushed(SyncDocumentChecklistCategoryJob::class, 1);

    $category->delete();
    $this->postJson("/api/document-checklist-categories/{$category->id}/sync")->assertNotFound();
});

it('AC-015: sync-logs returns at most 20 rows of the category, newest first', function () {
    Sanctum::actingAs(documentChecklistUserWith(['view']));
    $category = DocumentChecklistCategory::factory()->create();
    $other = DocumentChecklistCategory::factory()->create();
    foreach (range(1, 25) as $attempt) {
        DocumentChecklistCategorySyncLog::create([
            'document_checklist_category_id' => $category->id, 'operation' => 'upsert', 'outcome' => 'retrying',
            'http_status' => 503, 'attempt' => $attempt, 'message' => "try {$attempt}",
        ]);
    }
    DocumentChecklistCategorySyncLog::create([
        'document_checklist_category_id' => $other->id, 'operation' => 'upsert', 'outcome' => 'success', 'attempt' => 1,
    ]);

    $response = $this->getJson("/api/document-checklist-categories/{$category->id}/sync-logs")->assertOk();

    expect($response->json('data'))->toHaveCount(20)
        ->and($response->json('data.0.message'))->toBe('try 25')
        ->and($response->json('data.0'))->toHaveKeys(['id', 'operation', 'outcome', 'http_status', 'attempt', 'message', 'created_at']);
});

it('AC-011: checklist category_id must be an existing, non-deleted category', function (string $kind) {
    Sanctum::actingAs(documentChecklistUserWith(['create']));
    $deleted = DocumentChecklistCategory::factory()->create();
    $deleted->delete();

    $categoryId = $kind === 'deleted' ? $deleted->id : 999999;

    $this->postJson('/api/document-checklists', documentChecklistPayload(['category_id' => $categoryId]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
    expect(DocumentChecklist::count())->toBe(0);
})->with(['deleted', 'missing']);

it('AC-011: a valid category_id is saved and exposed, no field means no category', function () {
    Sanctum::actingAs(documentChecklistUserWith(['create', 'update', 'view']));
    $category = DocumentChecklistCategory::factory()->create(['name' => 'Mutui']);

    $created = $this->postJson('/api/document-checklists', documentChecklistPayload(['category_id' => $category->id]))
        ->assertCreated()
        ->assertJsonPath('data.category', ['id' => $category->id, 'name' => 'Mutui']);
    $id = $created->json('data.id');

    $this->getJson("/api/document-checklists/{$id}")->assertJsonPath('data.category.name', 'Mutui');

    $this->putJson("/api/document-checklists/{$id}", documentChecklistPayload(['items' => []]))
        ->assertOk()
        ->assertJsonPath('data.category', null);
    expect(DocumentChecklist::find($id)->category_id)->toBeNull();
});

it('AC-013: sync-all queues the categories first, then the checklists', function () {
    Sanctum::actingAs(documentChecklistUserWith(['update']));
    DocumentChecklistCategory::factory()->count(2)->create(['sync_status' => 'synced']);
    DocumentChecklistCategory::factory()->create()->delete();
    DocumentChecklist::factory()->count(3)->create(['sync_status' => 'synced']);
    DocumentChecklist::factory()->create()->delete();

    $this->postJson('/api/document-checklists/sync-all')
        ->assertStatus(202)
        ->assertJsonPath('data.queued', 3)
        ->assertJsonPath('data.queued_categories', 2);

    Queue::assertPushed(SyncDocumentChecklistCategoryJob::class, 2);
    Queue::assertPushed(SyncDocumentChecklistJob::class, 3);
    expect(DocumentChecklistCategory::where('sync_status', 'pending')->count())->toBe(2)
        ->and(DocumentChecklist::where('sync_status', 'pending')->count())->toBe(3);

    $order = collect(Queue::pushedJobs())->flatMap(fn (array $jobs, string $class) => array_fill(0, count($jobs), $class))->values()->all();
    expect($order)->toBe([
        ...array_fill(0, 2, SyncDocumentChecklistCategoryJob::class),
        ...array_fill(0, 3, SyncDocumentChecklistJob::class),
    ]);
});

it('AC-016: for-select returns only non-deleted categories ordered by sort_order, name', function () {
    Sanctum::actingAs(documentChecklistUserWith([]));
    DocumentChecklistCategory::factory()->create(['name' => 'Zeta', 'sort_order' => 1]);
    DocumentChecklistCategory::factory()->create(['name' => 'Beta', 'sort_order' => 2]);
    DocumentChecklistCategory::factory()->create(['name' => 'Alfa', 'sort_order' => 2]);
    DocumentChecklistCategory::factory()->create(['name' => 'Eliminata', 'sort_order' => 0])->delete();

    $response = $this->getJson('/api/document-checklist-categories/for-select')->assertOk();

    expect(collect($response->json('items'))->pluck('label')->all())->toBe(['Zeta', 'Alfa', 'Beta'])
        ->and($response->json('items.0'))->toHaveKeys(['id', 'label']);

    $this->getJson('/api/document-checklist-categories/for-select?search=lf')
        ->assertOk()->assertJsonCount(1, 'items');
});

it('AC-016: the menu entry shows under config-documents only with document-checklists.view', function () {
    Permission::findOrCreate('document-checklists.view');

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('document-checklist-categories');

    $withView = User::factory()->create();
    $withView->givePermissionTo('document-checklists.view');
    Sanctum::actingAs($withView);

    $node = collect($this->getJson('/api/navigation')->json('data'))
        ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])])
        ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])])
        ->firstWhere('key', 'document-checklist-categories');

    expect($node)->not->toBeNull()
        ->and($node['route'])->toBe('/document-checklist-categories')
        ->and($node['icon'])->toBe('list-tree');
});

<?php

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
| `document-checklist-categories` grid and the category column of the checklist
| grid (spec 0410): AC-014, plus the relations introduced by BE-01.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('AC-014: rows carry the contract columns, skip deleted categories and sort by sort_order, name', function () {
    Sanctum::actingAs(documentChecklistUserWith(['viewAny']));
    $zeta = DocumentChecklistCategory::factory()->create(['name' => 'Zeta', 'sort_order' => 0, 'sync_status' => 'failed', 'sync_error' => 'Nome in uso']);
    DocumentChecklistCategory::factory()->create(['name' => 'Beta', 'sort_order' => 1]);
    DocumentChecklistCategory::factory()->create(['name' => 'Alfa', 'sort_order' => 1]);
    DocumentChecklistCategory::factory()->create(['name' => 'Eliminata'])->delete();
    DocumentChecklist::factory()->count(2)->create(['category_id' => $zeta->id]);
    DocumentChecklist::factory()->create(['category_id' => $zeta->id])->delete();

    $response = $this->postJson('/api/tables/document-checklist-categories/rows', ['startRow' => 0, 'endRow' => 50])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Zeta', 'Alfa', 'Beta']);

    $row = $response->json('items.0');
    expect($row)->toHaveKeys(['id', 'name', 'description', 'sort_order', 'checklists_count', 'sync_status', 'sync_error', 'synced_at', 'created_at', 'actions'])
        ->and($row['checklists_count'])->toBe(2)
        ->and($row['sync_status'])->toBe('failed')
        ->and($row['sync_error'])->toBe('Nome in uso');
});

it('AC-014: the sync action is offered only with update, delete only with delete', function () {
    DocumentChecklistCategory::factory()->create();

    Sanctum::actingAs(documentChecklistUserWith(['viewAny', 'view', 'delete', 'viewActivity']));
    $actions = $this->postJson('/api/tables/document-checklist-categories/rows', ['startRow' => 0, 'endRow' => 10])
        ->assertOk()->json('items.0.actions');
    expect($actions)->toBe(['view', 'delete', 'activity']);

    Sanctum::actingAs(documentChecklistUserWith(['viewAny', 'update']));
    $actions = $this->postJson('/api/tables/document-checklist-categories/rows', ['startRow' => 0, 'endRow' => 10])
        ->assertOk()->json('items.0.actions');
    expect($actions)->toBe(['sync']);
});

it('AC-014: filters by name (text) and sync_status (set)', function () {
    Sanctum::actingAs(documentChecklistUserWith(['viewAny']));
    DocumentChecklistCategory::factory()->create(['name' => 'Mutui', 'sync_status' => 'synced']);
    DocumentChecklistCategory::factory()->create(['name' => 'Polizze', 'sync_status' => 'failed']);

    $this->postJson('/api/tables/document-checklist-categories/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'muT']],
    ])->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Mutui');

    $this->postJson('/api/tables/document-checklist-categories/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['sync_status' => ['filterType' => 'set', 'values' => ['failed']]],
    ])->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Polizze');
});

it('AC-014: the checklist grid shows category_name and filters and sorts on it', function () {
    Sanctum::actingAs(documentChecklistUserWith(['viewAny']));
    $mutui = DocumentChecklistCategory::factory()->create(['name' => 'Mutui']);
    $polizze = DocumentChecklistCategory::factory()->create(['name' => 'Polizze']);
    DocumentChecklist::factory()->create(['name' => 'Con mutui', 'category_id' => $mutui->id]);
    DocumentChecklist::factory()->create(['name' => 'Con polizze', 'category_id' => $polizze->id]);
    DocumentChecklist::factory()->create(['name' => 'Senza']);

    $all = $this->postJson('/api/tables/document-checklists/rows', ['startRow' => 0, 'endRow' => 10])->assertOk();
    expect(collect($all->json('items'))->pluck('category_name', 'name')->all())
        ->toBe(['Con mutui' => 'Mutui', 'Con polizze' => 'Polizze', 'Senza' => null]);

    $this->postJson('/api/tables/document-checklists/rows', [
        'startRow' => 0, 'endRow' => 10,
        'filterModel' => ['category_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'poliz']],
    ])->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Con polizze');

    $sorted = $this->postJson('/api/tables/document-checklists/rows', [
        'startRow' => 0, 'endRow' => 10,
        'sortModel' => [['colId' => 'category_name', 'sort' => 'desc']],
    ])->assertOk();
    expect($sorted->json('items.0.category_name'))->toBe('Polizze');
});

it('BE-01: the category relations hold and the checklist exposes its category', function () {
    $category = DocumentChecklistCategory::factory()->create();
    $checklist = DocumentChecklist::factory()->create(['category_id' => $category->id]);
    DocumentChecklist::factory()->create(['category_id' => $category->id])->delete();

    expect($checklist->category->is($category))->toBeTrue()
        ->and($category->checklists()->count())->toBe(1)
        ->and($category->checklists()->withTrashed()->count())->toBe(2)
        ->and($category->getMorphClass())->toBe('document_checklist_category');
});

it('BE-01: a category with checklists cannot be hard deleted (no cascade)', function () {
    $category = DocumentChecklistCategory::factory()->create();
    DocumentChecklist::factory()->create(['category_id' => $category->id]);

    expect(fn () => $category->forceDelete())->toThrow(QueryException::class);
});

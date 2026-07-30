<?php

use App\Jobs\GenerateExportJob;
use App\Models\DocumentLayout;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Backend-driven table for the `document-layouts` domain (spec 0069, MT-5):
// GET /api/tables/document-layouts/columns + POST /api/tables/document-layouts/rows.
// AC-070 (columns/flags), AC-071 (search), AC-072 (filters/sort/pagination),
// AC-073 (row actions), AC-074 (export). Inline edit (AC-075) is covered
// separately by DocumentLayoutInlineEditTest.

if (! function_exists('documentLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-layouts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-layouts.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-070 — columns config, frozen order + flags
// ---------------------------------------------------------------------------

it('GET /api/tables/document-layouts/columns: 403 without viewAny, 200 with the 8 frozen columns (AC-070)', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/document-layouts/columns')->assertForbidden();

    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/document-layouts/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('document-layouts')
        ->and($data['defaultSort'])->toBe([['columnId' => 'name', 'direction' => 'asc']])
        ->and($data['defaultPagination'])->toBe(['limit' => 25])
        // `searchable` is a top-level allow-list of column ids (spec 0009).
        ->and($data['searchable'])->toBe(['name', 'code']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'code', 'module', 'is_default', 'is_active', 'description', 'created_at', 'updated_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['code']['sortable'])->toBeTrue()
        ->and($columns['code']['filterType'])->toBe('text')
        ->and($columns['module']['sortable'])->toBeTrue()
        ->and($columns['module']['filterType'])->toBe('text')
        ->and($columns['is_default']['type'])->toBe('boolean')
        ->and($columns['is_active']['type'])->toBe('boolean')
        ->and($columns['description']['sortable'])->toBeFalse()
        ->and($columns['description']['filterable'])->toBeTrue()
        ->and($columns['created_at']['type'])->toBe('datetime')
        ->and($columns['updated_at']['type'])->toBe('datetime');
});

it('columns: is_active resolves editable:true, is_default stays NON editable, for an actor holding document-layouts.update (AC-070, D-7)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/document-layouts/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['is_active']['editable'])->toBeTrue();

    // is_default is deliberately excluded (D-7: its write must go through
    // DocumentLayoutDefaultManager, never a bare mass-assignment).
    foreach ($columns->except('is_active') as $id => $column) {
        expect($column['editable'] ?? false)->toBeFalse("column {$id} unexpectedly editable");
    }
});

// ---------------------------------------------------------------------------
// AC-071 — global search on name OR code
// ---------------------------------------------------------------------------

it('rows: search on a term present only in `code` finds the row, likewise for `name`, absent from both finds nothing (AC-071)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    DocumentLayout::factory()->create(['name' => 'Alpha Layout', 'code' => 'uniquecodezz']);
    DocumentLayout::factory()->create(['name' => 'Beta Layout', 'code' => 'othercode']);
    Sanctum::actingAs($actor);

    $byCode = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'uniquecodezz'])->assertOk();
    expect(collect($byCode->json('items'))->pluck('code')->all())->toBe(['uniquecodezz']);

    $byName = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'Alpha Layout'])->assertOk();
    expect(collect($byName->json('items'))->pluck('name')->all())->toBe(['Alpha Layout']);

    $none = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'nonexistent-term'])->assertOk();
    expect($none->json('items'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-072 — module/is_active/is_default filters, default sort + pagination
// ---------------------------------------------------------------------------

it('rows: advanced filter on module returns only layouts of the chosen module (AC-072)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    DocumentLayout::factory()->create(['name' => 'Quotes Layout', 'module' => 'quotes']);
    Sanctum::actingAs($actor);

    $matching = $this->postJson('/api/tables/document-layouts/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['module' => 'quotes'],
    ])->assertOk();
    expect(collect($matching->json('items'))->pluck('name')->all())->toBe(['Quotes Layout']);

    // A module no layout belongs to must exclude every existing row.
    $nonMatching = $this->postJson('/api/tables/document-layouts/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['module' => 'invoices'],
    ])->assertOk();
    expect($nonMatching->json('items'))->toBe([]);
});

it('rows: filter on is_active returns only matching rows (AC-072)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    DocumentLayout::factory()->create(['name' => 'Active Row', 'is_active' => true]);
    DocumentLayout::factory()->create(['name' => 'Inactive Row', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/document-layouts/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'filter' => false]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toContain('Inactive Row')->and($names)->not->toContain('Active Row');
});

it('rows: advanced filter on is_default returns only the predefinito layout (AC-072)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    DocumentLayout::factory()->create(['name' => 'Default Layout', 'is_default' => true]);
    DocumentLayout::factory()->create(['name' => 'Regular Layout', 'is_default' => false]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/document-layouts/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['is_default' => true],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Default Layout']);
});

it('rows: sorts by name asc/desc, defaults to name asc, limit 25 (AC-072)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    DocumentLayout::factory()->create(['name' => 'Zeta layout']);
    DocumentLayout::factory()->create(['name' => 'Alpha layout']);
    Sanctum::actingAs($actor);

    $byNameAsc = $this->postJson('/api/tables/document-layouts/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'name', 'sort' => 'asc']],
    ])->assertOk();
    $namesAsc = collect($byNameAsc->json('items'))->pluck('name')->all();
    expect(array_search('Alpha layout', $namesAsc, true))->toBeLessThan(array_search('Zeta layout', $namesAsc, true));

    $byNameDesc = $this->postJson('/api/tables/document-layouts/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'name', 'sort' => 'desc']],
    ])->assertOk();
    $namesDesc = collect($byNameDesc->json('items'))->pluck('name')->all();
    expect(array_search('Zeta layout', $namesDesc, true))->toBeLessThan(array_search('Alpha layout', $namesDesc, true));

    // Default (no sortModel): ordered name asc.
    $default = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $defaultNames = collect($default->json('items'))->pluck('name')->all();
    expect(array_search('Alpha layout', $defaultNames, true))->toBeLessThan(array_search('Zeta layout', $defaultNames, true));

    DocumentLayout::factory()->count(28)->create();
    $paged = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    expect($paged->json('pagination.total'))->toBe(30)
        ->and($paged->json('items'))->toHaveCount(25);
});

// ---------------------------------------------------------------------------
// AC-073 — row actions gated per permission, delete carries confirm/danger
// ---------------------------------------------------------------------------

it('rows: view/edit/delete/activity actions present only with the matching permission (AC-073)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']);
    DocumentLayout::factory()->create(['name' => 'Full Actions']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Full Actions');

    expect($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete', 'activity']);
});

it('rows: an actor with only viewAny sees no row actions (AC-073)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    DocumentLayout::factory()->create(['name' => 'No Actions']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/document-layouts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'No Actions');

    expect($row['actions'])->toBe([]);
});

it('columns: the delete action config carries confirm=true and type=danger (AC-073)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'delete']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/document-layouts/columns')->assertOk()->json('data');
    $deleteAction = collect($data['actions'])->firstWhere('key', 'delete');

    expect($deleteAction)->not->toBeNull()
        ->and($deleteAction['confirm'])->toBeTrue()
        ->and($deleteAction['type'])->toBe('danger');
});

// ---------------------------------------------------------------------------
// AC-074 — export "for free" via config/tables.php registration
// ---------------------------------------------------------------------------

it('export: document-layouts is registered in the generic export engine, no dedicated code (AC-074)', function () {
    Queue::fake();
    $actor = documentLayoutUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/document-layouts', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'document-layouts');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without document-layouts.export, no export job pushed (AC-074)', function () {
    Queue::fake();
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/document-layouts', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

// ---------------------------------------------------------------------------
// Advanced filters catalogue shape
// ---------------------------------------------------------------------------

it('advanced filters: module (Enum), is_active (Switch), is_default (Switch) are exposed with the frozen types (AC-072)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/document-layouts/columns')->assertOk()->json('data');
    $filters = collect($data['advancedFilters'])->keyBy('name');

    expect($filters['module']['type'])->toBe('enum')
        ->and($filters['is_active']['type'])->toBe('switch')
        ->and($filters['is_default']['type'])->toBe('switch');
});

// Referenced so PHPStan/IDE navigation resolves the factory helper used above
// without an unused-import warning when only `DocumentLayout::factory()` is
// exercised directly in some suites.
if (! class_exists(DocumentLayoutFactory::class)) {
    throw new RuntimeException('DocumentLayoutFactory must exist for this suite.');
}

<?php

use App\Models\BusinessFunction;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('projectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("projects.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("projects.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/projects/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/projects/columns')->assertForbidden();

    $actor = projectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/projects/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('projects')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['code', 'name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe([
        'id', 'code', 'name', 'pipeline_status', 'business_function',
        'country', 'state', 'province', 'city', 'geo_scope', 'product_category', 'partner', 'operational_site',
        'start_date', 'end_date', 'total_budget', 'target_lead', 'created_at',
    ]);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['pipeline_status']['sortable'])->toBeTrue()
        ->and($columns['pipeline_status']['filterType'])->toBe('set');
});

// ---------------------------------------------------------------------------
// AC-013 — country/province/city are filterable derived columns (spec 0027);
// geo_scope is DISPLAY-ONLY (D-2)
// ---------------------------------------------------------------------------

it('country/province/city are filterable derived columns, geo_scope is display-only (AC-013)', function () {
    $actor = projectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/projects/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    foreach (['country', 'province', 'city'] as $columnId) {
        expect($columns[$columnId]['filterable'])->toBeTrue()
            ->and($columns[$columnId]['filterType'])->toBe('set');
    }

    expect($columns['geo_scope']['sortable'])->toBeFalse()
        ->and($columns['geo_scope']['filterable'])->toBeFalse();

    $filterColumnIds = collect($data['filters'])->pluck('columnId')->all();
    expect($filterColumnIds)->not->toContain('geo_scope');
});

it('the derived country set filter matches by the related country name (AC-013)', function () {
    $actor = projectUserWith(['viewAny']);
    $geo = geoChain();
    $otherCountry = Country::factory()->create(['name' => 'Francia']);
    Project::factory()->create(['name' => 'Progetto IT', 'country_id' => $geo['country']->id]);
    Project::factory()->create(['name' => 'Projet FR', 'country_id' => $otherCountry->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['country' => ['filterType' => 'set', 'values' => ['Italia']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Progetto IT']);
});

it('rows: geo_scope reflects the finest non-null geo level (AC-013, D-2)', function () {
    $actor = projectUserWith(['viewAny']);
    $geo = geoChain();
    $project = Project::factory()->create([
        'name' => 'City Scoped',
        'country_id' => $geo['country']->id,
        'state_id' => $geo['state']->id,
        'province_id' => $geo['province']->id,
        'city_id' => $geo['city']->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/projects/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $project->id);

    expect($row['geo_scope'])->toBe('city')
        ->and($row['city'])->toMatchArray(['id' => $geo['city']->id, 'name' => 'Milano']);
});

// ---------------------------------------------------------------------------
// AC-031 — sort on the derived pipeline_status column
// ---------------------------------------------------------------------------

it('sorts rows by the derived pipeline_status name via a correlated subquery (AC-031)', function () {
    $actor = projectUserWith(['viewAny']);
    $zed = PipelineStatus::factory()->create(['name' => 'Zed Status']);
    $amy = PipelineStatus::factory()->create(['name' => 'Amy Status']);
    Project::factory()->create(['name' => 'Z-project', 'pipeline_status_id' => $zed->id]);
    Project::factory()->create(['name' => 'A-project', 'pipeline_status_id' => $amy->id]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'pipeline_status', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-project', $names, true))->toBeLessThan(array_search('Z-project', $names, true));
});

it('a sort colId outside the allow-list returns 422, never a 500 / raw SQL (AC-031)', function () {
    $actor = projectUserWith(['viewAny']);
    Project::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'not_a_real_column; DROP TABLE projects;--', 'sort' => 'asc']],
    ])->assertStatus(422)->assertJsonValidationErrors('sortModel.0.colId');

    // Defence in depth: the table must still exist and be queryable.
    expect(Project::count())->toBe(2);
});

it('the derived pipeline_status set filter matches by the related status name', function () {
    $actor = projectUserWith(['viewAny']);
    $commercial = PipelineStatus::factory()->create(['name' => 'Commercial']);
    $technical = PipelineStatus::factory()->create(['name' => 'Technical']);
    Project::factory()->create(['name' => 'Project A', 'pipeline_status_id' => $commercial->id]);
    Project::factory()->create(['name' => 'Project B', 'pipeline_status_id' => $technical->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['pipeline_status' => ['filterType' => 'set', 'values' => ['Commercial']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Project A']);
});

// ---------------------------------------------------------------------------
// duplicate row action — gated on projects.create (not a per-row ability),
// mirrors LeadsTableActionsTest's convert_to_opportunity pattern.
// ---------------------------------------------------------------------------

it('catalogue includes duplicate for an actor with projects.create', function () {
    $actor = projectUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $actions = collect($this->getJson('/api/tables/projects/columns')->assertOk()->json('data.actions'));
    $entry = $actions->firstWhere('key', 'duplicate');

    expect($entry)->not->toBeNull()
        ->and($entry)->toMatchArray([
            'key' => 'duplicate',
            'label' => 'actions.duplicate',
            'type' => 'action',
            'confirm' => false,
        ])
        ->and($entry)->not->toHaveKey('permission');
});

it('catalogue omits duplicate for an actor without projects.create', function () {
    $actor = projectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $actionKeys = collect($this->getJson('/api/tables/projects/columns')->assertOk()->json('data.actions'))
        ->pluck('key')->all();

    expect($actionKeys)->not->toContain('duplicate');
});

it('row.actions contains duplicate for an actor with projects.create', function () {
    $actor = projectUserWith(['viewAny', 'create']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/projects/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $project->id)['actions'])->toContain('duplicate');
});

it('row.actions omits duplicate for an actor without projects.create', function () {
    $actor = projectUserWith(['viewAny']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/projects/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $project->id)['actions'])->not->toContain('duplicate');
});

// ---------------------------------------------------------------------------
// AC-025/AC-026 — business_function/product_category AGGREGATED (to-many)
// columns: comma-joined display, set filter, distinct values, never sortable
// ---------------------------------------------------------------------------

it('rows: business_function/product_category display the comma-joined names of every line (AC-025)', function () {
    $actor = projectUserWith(['viewAny']);
    $functionA = BusinessFunction::factory()->create(['name' => 'Marketing']);
    $functionB = BusinessFunction::factory()->create(['name' => 'Sales']);
    $categoryA = ProductCategory::factory()->create(['business_function_id' => $functionA->id, 'name' => 'Widgets']);
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $functionB->id, 'name' => 'Gadgets']);
    $project = Project::factory()->create(['name' => 'Multi Line']);
    $project->productLines()->create(['business_function_id' => $functionA->id, 'product_category_id' => $categoryA->id]);
    $project->productLines()->create(['business_function_id' => $functionB->id, 'product_category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/projects/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $project->id);

    expect($row['business_function'])->toBe('Marketing, Sales')
        ->and($row['product_category'])->toBe('Widgets, Gadgets');
});

it('rows: the product_category set filter matches via whereHas on project_product_lines, never sorts (AC-025/AC-026)', function () {
    $actor = projectUserWith(['viewAny']);
    $category = ProductCategory::factory()->create(['name' => 'Widgets']);
    $otherCategory = ProductCategory::factory()->create(['name' => 'Gadgets']);
    $matching = Project::factory()->create(['name' => 'Has Widgets']);
    $matching->productLines()->create(['business_function_id' => BusinessFunction::factory()->create()->id, 'product_category_id' => $category->id]);
    $nonMatching = Project::factory()->create(['name' => 'Has Gadgets']);
    $nonMatching->productLines()->create(['business_function_id' => BusinessFunction::factory()->create()->id, 'product_category_id' => $otherCategory->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['product_category' => ['filterType' => 'set', 'values' => ['Widgets']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Has Widgets']);

    // Never sortable (AC-025): an attempted sort is rejected, not silently applied.
    $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'product_category', 'sort' => 'asc']],
    ])->assertStatus(422)->assertJsonValidationErrors('sortModel.0.colId');
});

it('the product_category distinct values are the related names, scoped by the page query, no whereRaw (AC-026)', function () {
    $actor = projectUserWith(['viewAny']);
    $category = ProductCategory::factory()->create(['name' => 'Widgets']);
    $project = Project::factory()->create();
    $project->productLines()->create(['business_function_id' => BusinessFunction::factory()->create()->id, 'product_category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/projects/values', ['columnId' => 'product_category'])
        ->assertOk();

    expect($response->json('data.values'))->toContain('Widgets');
});

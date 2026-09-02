<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('campaignUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/campaigns/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = campaignUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/campaigns/columns')->assertForbidden();

    $actor = campaignUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/campaigns/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('campaigns')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['code', 'name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe([
        'id', 'code', 'project', 'name', 'pipeline_status', 'business_function', 'product_category',
        'country', 'state', 'province', 'city', 'geo_scope', 'operational_site',
        'start_date', 'end_date', 'total_budget', 'target_lead', 'created_at',
    ]);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['type'])->toBe('number')
        ->and($columns['id']['visible'])->toBeFalse()
        ->and($columns['id']['sortable'])->toBeTrue()
        ->and($columns['id']['filterable'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBeNull()
        ->and($columns['project']['sortable'])->toBeTrue()
        ->and($columns['pipeline_status']['sortable'])->toBeFalse()
        ->and($columns['pipeline_status']['filterType'])->toBe('set')
        // Spec 0094, AC-025/AC-027: AGGREGATED (to-many) columns, never sortable.
        ->and($columns['business_function']['sortable'])->toBeFalse()
        ->and($columns['business_function']['filterType'])->toBe('set')
        ->and($columns['product_category']['sortable'])->toBeFalse()
        ->and($columns['product_category']['filterType'])->toBe('set');
});

// ---------------------------------------------------------------------------
// AC-013 — the 4 geo columns + geo_scope are DISPLAY-ONLY (spec 0027)
// ---------------------------------------------------------------------------

it('the geo columns and geo_scope are neither sortable nor filterable (AC-013)', function () {
    $actor = campaignUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/campaigns/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    foreach (['country', 'state', 'province', 'city', 'geo_scope'] as $columnId) {
        expect($columns[$columnId]['sortable'])->toBeFalse()
            ->and($columns[$columnId]['filterable'])->toBeFalse();
    }

    $filterColumnIds = collect($data['filters'])->pluck('columnId')->all();
    foreach (['country', 'state', 'province', 'city', 'geo_scope'] as $columnId) {
        expect($filterColumnIds)->not->toContain($columnId);
    }
});

it('rows: a linked campaign shows the MERGED geo (own refinement over the project\'s) (AC-013, BR-5)', function () {
    $actor = campaignUserWith(['viewAny']);
    $geo = geoChain();
    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);
    $campaign = Campaign::factory()->forProject($project)->create([
        'name' => 'Refined Row',
        'state_id' => $geo['state']->id,
        'city_id' => $geo['city']->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($row['country'])->toMatchArray(['id' => $geo['country']->id, 'name' => 'Italia'])
        ->and($row['state'])->toMatchArray(['id' => $geo['state']->id, 'name' => 'Lombardia'])
        ->and($row['city'])->toMatchArray(['id' => $geo['city']->id, 'name' => 'Milano'])
        ->and($row['geo_scope'])->toBe('city');
});

// ---------------------------------------------------------------------------
// AC-032 — a linked campaign's pipeline_status column shows the PROJECT's
// status (COALESCE read-through), and the set filter finds it
// ---------------------------------------------------------------------------

it('rows: a linked campaign\'s pipeline_status shows the project\'s status (AC-032)', function () {
    $actor = campaignUserWith(['viewAny']);
    $status = PipelineStatus::factory()->create(['name' => 'On Track']);
    $project = Project::factory()->create(['pipeline_status_id' => $status->id]);
    $campaign = Campaign::factory()->forProject($project)->create(['name' => 'Linked Row']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($row['pipeline_status'])->toMatchArray(['id' => $status->id, 'name' => 'On Track']);
});

it('rows: the pipeline_status set filter finds a linked campaign via the project\'s status (AC-032)', function () {
    $actor = campaignUserWith(['viewAny']);
    $status = PipelineStatus::factory()->create(['name' => 'Escalated']);
    $otherStatus = PipelineStatus::factory()->create(['name' => 'Closed']);
    $project = Project::factory()->create(['pipeline_status_id' => $status->id]);
    $linked = Campaign::factory()->forProject($project)->create(['name' => 'Matches']);
    $otherProject = Project::factory()->create(['pipeline_status_id' => $otherStatus->id]);
    Campaign::factory()->forProject($otherProject)->create(['name' => 'Does Not Match']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['pipeline_status' => ['filterType' => 'set', 'values' => ['Escalated']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$linked->id]);
});

it('rows: a standalone campaign\'s pipeline_status shows its OWN status', function () {
    $actor = campaignUserWith(['viewAny']);
    $status = PipelineStatus::factory()->create(['name' => 'Standalone Status']);
    $campaign = Campaign::factory()->create(['name' => 'Standalone Row', 'pipeline_status_id' => $status->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($row['pipeline_status'])->toMatchArray(['id' => $status->id, 'name' => 'Standalone Status']);
});

// ---------------------------------------------------------------------------
// duplicate row action — gated on campaigns.create (not a per-row ability),
// mirrors LeadsTableActionsTest's convert_to_opportunity pattern.
// ---------------------------------------------------------------------------

it('catalogue includes duplicate for an actor with campaigns.create', function () {
    $actor = campaignUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $actions = collect($this->getJson('/api/tables/campaigns/columns')->assertOk()->json('data.actions'));
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

it('catalogue omits duplicate for an actor without campaigns.create', function () {
    $actor = campaignUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $actionKeys = collect($this->getJson('/api/tables/campaigns/columns')->assertOk()->json('data.actions'))
        ->pluck('key')->all();

    expect($actionKeys)->not->toContain('duplicate');
});

it('row.actions contains duplicate for an actor with campaigns.create', function () {
    $actor = campaignUserWith(['viewAny', 'create']);
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $campaign->id)['actions'])->toContain('duplicate');
});

it('row.actions omits duplicate for an actor without campaigns.create', function () {
    $actor = campaignUserWith(['viewAny']);
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $campaign->id)['actions'])->not->toContain('duplicate');
});

// ---------------------------------------------------------------------------
// AC-025/AC-026/AC-027 — business_function/product_category AGGREGATED
// (to-many) columns, own-or-through-project (BR-2, mirrors pipeline_status)
// ---------------------------------------------------------------------------

it('rows: a linked campaign shows the PROJECT\'s product lines, comma-joined (AC-027)', function () {
    $actor = campaignUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Marketing']);
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id, 'name' => 'Widgets']);
    $project = Project::factory()->create();
    $project->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $category->id]);
    $campaign = Campaign::factory()->forProject($project)->create(['name' => 'Linked Row']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($row['business_function'])->toBe('Marketing')
        ->and($row['product_category'])->toBe('Widgets');
});

it('rows: a standalone campaign shows its OWN product lines (AC-025)', function () {
    $actor = campaignUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id, 'name' => 'Gadgets']);
    $campaign = Campaign::factory()->create(['name' => 'Standalone Row']);
    $campaign->productLines()->delete();
    $campaign->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($row['business_function'])->toBe('Sales')
        ->and($row['product_category'])->toBe('Gadgets');
});

it('rows: the product_category set filter finds a linked campaign via the project\'s rows, never sorts (AC-025/AC-026/AC-027)', function () {
    $actor = campaignUserWith(['viewAny']);
    $category = ProductCategory::factory()->create(['name' => 'Widgets']);
    $project = Project::factory()->create();
    $project->productLines()->create(['business_function_id' => BusinessFunction::factory()->create()->id, 'product_category_id' => $category->id]);
    $linked = Campaign::factory()->forProject($project)->create(['name' => 'Matches']);
    Campaign::factory()->create(['name' => 'Does Not Match']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['product_category' => ['filterType' => 'set', 'values' => ['Widgets']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$linked->id]);

    $this->postJson('/api/tables/campaigns/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'product_category', 'sort' => 'asc']],
    ])->assertStatus(422)->assertJsonValidationErrors('sortModel.0.colId');
});

it('the business_function distinct values union own AND linked-project rows, no whereRaw (AC-026)', function () {
    $actor = campaignUserWith(['viewAny']);
    $ownFunction = BusinessFunction::factory()->create(['name' => 'Standalone Function']);
    $standalone = Campaign::factory()->create();
    $standalone->productLines()->delete();
    $standalone->productLines()->create(['business_function_id' => $ownFunction->id, 'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $ownFunction->id])->id]);

    $projectFunction = BusinessFunction::factory()->create(['name' => 'Linked Function']);
    $project = Project::factory()->create();
    $project->productLines()->create(['business_function_id' => $projectFunction->id, 'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $projectFunction->id])->id]);
    Campaign::factory()->forProject($project)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/values', ['columnId' => 'business_function'])->assertOk();

    expect($response->json('data.values'))
        ->toContain('Standalone Function')
        ->toContain('Linked Function');
});

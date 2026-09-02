<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// BR-5 geo refinement (spec 0027, replaces BR-2 for the geo levels): a
// campaign linked to a project INHERITS every geo level the project has
// filled, and may REFINE the levels the project left empty. Extracted out of
// CampaignCrudTest.php (file-size split, engineering.md §6).

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

if (! function_exists('standaloneClassificationFields')) {
    /**
     * `pipeline_status_id` plus `product_lines` (spec 0094), required for a
     * standalone campaign (state_id LEFT this group — spec 0027, D-3). Local
     * copy mirroring CampaignCrudTest's (each test file guards its own,
     * since file load order across the suite is not guaranteed).
     *
     * @return array<string, mixed>
     */
    function standaloneClassificationFields(): array
    {
        // Coherent pair (spec 0023 REV): the category sits under the business function.
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'pipeline_status_id' => PipelineStatus::factory()->create()->id,
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-004 — a level the project fills is prohibited; a level it leaves empty
// is writable, and the response merges (own-or-project) + geo_locked_levels
// ---------------------------------------------------------------------------

it('create: linked to a country-only project, sending country_id -> 422 prohibited (AC-004, BR-5)', function () {
    $actor = campaignUserWith(['create']);
    $geo = geoChain();
    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Prohibited Country',
        'project_id' => $project->id,
        'country_id' => $geo['country']->id,
    ])->assertStatus(422)->assertJsonValidationErrors('country_id');

    expect(Campaign::count())->toBe(0);
});

it('create: linked to a country-only project, refining state+city -> 201, merged geo + geo_locked_levels (AC-004, BR-5)', function () {
    $actor = campaignUserWith(['create']);
    $geo = geoChain();
    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/campaigns', [
        'name' => 'Refined Campaign',
        'project_id' => $project->id,
        'state_id' => $geo['state']->id,
        'city_id' => $geo['city']->id,
        ...campaignStoreDates(),
    ])->assertCreated()
        ->assertJsonPath('data.country_id', $geo['country']->id)
        ->assertJsonPath('data.state_id', $geo['state']->id)
        ->assertJsonPath('data.city_id', $geo['city']->id)
        ->assertJsonPath('data.geo_scope', 'city')
        ->assertJsonPath('data.geo_locked_levels', ['country']);

    $campaignId = $response->json('data.id');
    $this->assertDatabaseHas('campaigns', [
        'id' => $campaignId,
        'country_id' => null,
        'state_id' => $geo['state']->id,
        'city_id' => $geo['city']->id,
    ]);
});

// ---------------------------------------------------------------------------
// AC-005 — BR-4 enforced on the MERGED tuple: a campaign under an Italian
// project cannot pick a state of another country
// ---------------------------------------------------------------------------

it('create: linked campaign cannot pick a state of another country -> 422 (AC-005, BR-4 on the merged tuple)', function () {
    $actor = campaignUserWith(['create']);
    $geo = geoChain();
    $foreignState = State::factory()->create();
    $project = Project::factory()->create(['country_id' => $geo['country']->id, 'state_id' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Cross Country',
        'project_id' => $project->id,
        'state_id' => $foreignState->id,
    ])->assertStatus(422)->assertJsonValidationErrors('state_id');

    expect(Campaign::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-006 — a project that fills every level: all four prohibited on write,
// geo_locked_levels lists all four read-side
// ---------------------------------------------------------------------------

it('create: linked to a project that fills every geo level -> all 4 prohibited (AC-006, BR-5)', function () {
    $actor = campaignUserWith(['create']);
    $geo = geoChain();
    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => $geo['state']->id,
        'province_id' => $geo['province']->id,
        'city_id' => $geo['city']->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Fully Locked',
        'project_id' => $project->id,
        'city_id' => $geo['city']->id,
    ])->assertStatus(422)->assertJsonValidationErrors('city_id');

    expect(Campaign::count())->toBe(0);
});

it('show: a campaign linked to a fully-geo project reports geo_locked_levels with all 4 (AC-006)', function () {
    $actor = campaignUserWith(['view']);
    $geo = geoChain();
    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => $geo['state']->id,
        'province_id' => $geo['province']->id,
        'city_id' => $geo['city']->id,
    ]);
    $campaign = Campaign::factory()->forProject($project)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/campaigns/{$campaign->id}")
        ->assertOk()
        ->assertJsonPath('data.geo_locked_levels', ['country', 'state', 'province', 'city'])
        ->assertJsonPath('data.geo_scope', 'city')
        ->assertJsonPath('data.city.name', 'Milano');
});

// ---------------------------------------------------------------------------
// AC-007 — a standalone campaign behaves exactly like a project (BR-4 only)
// ---------------------------------------------------------------------------

it('create: a standalone campaign with a state not belonging to its country -> 422 on state_id (AC-007, BR-4)', function () {
    $actor = campaignUserWith(['create']);
    $geo = geoChain();
    $otherCountry = Country::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(standaloneClassificationFields(), [
        'name' => 'Inconsistent Standalone Geo',
        'country_id' => $otherCountry->id,
        'state_id' => $geo['state']->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('state_id');

    expect(Campaign::count())->toBe(0);
});

it('create: a standalone campaign with a full consistent geo chain -> 201, geo_scope=city (AC-007)', function () {
    $actor = campaignUserWith(['create']);
    $geo = geoChain();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(standaloneClassificationFields(), campaignStoreDates(), [
        'name' => 'Standalone City Scoped',
        'country_id' => $geo['country']->id,
        'state_id' => $geo['state']->id,
        'province_id' => $geo['province']->id,
        'city_id' => $geo['city']->id,
    ]))->assertCreated()
        ->assertJsonPath('data.geo_scope', 'city')
        ->assertJsonPath('data.geo_locked_levels', []);
});

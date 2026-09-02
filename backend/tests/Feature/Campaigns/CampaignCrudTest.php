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

/**
 * The BR-2 fields required for a standalone campaign: `pipeline_status_id`
 * plus `product_lines` (spec 0094 — REPLACING the former
 * `business_function_id`/`product_category_id` scalars, at least one row).
 * `state_id` LEFT this group (spec 0027, D-3): it now follows BR-5 like
 * every other geo level, so a standalone campaign only needs `country_id`
 * (see standaloneCampaignFields() below) alongside these two.
 *
 * @return array<string, mixed>
 */
if (! function_exists('standaloneClassificationFields')) {
    function standaloneClassificationFields(): array
    {
        return [
            'pipeline_status_id' => PipelineStatus::factory()->create()->id,
            'product_lines' => [campaignCoherentProductLine()],
        ];
    }
}

if (! function_exists('campaignCoherentProductLine')) {
    /**
     * One coherent {business_function_id, product_category_id} row (spec
     * 0023 REV pairing rule): the category is created UNDER the business
     * function, so its effective business function matches — the write-side
     * coherence rule (ProductLineSetValidator) rejects a mismatched pair.
     *
     * @return array{business_function_id: int, product_category_id: int}
     */
    function campaignCoherentProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'business_function_id' => $businessFunction->id,
            'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
        ];
    }
}

/**
 * The start/end planning dates, now required on every campaign (linked or
 * standalone — dates are the campaign's own, never inherited). Spread into a
 * store payload to satisfy the required rules.
 *
 * @return array<string, string>
 */
if (! function_exists('campaignStoreDates')) {
    function campaignStoreDates(): array
    {
        return ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'];
    }
}

if (! function_exists('standaloneCampaignFields')) {
    function standaloneCampaignFields(): array
    {
        return array_merge(
            standaloneClassificationFields(),
            ['country_id' => Country::factory()->create()->id],
            campaignStoreDates(),
        );
    }
}

// ---------------------------------------------------------------------------
// AC-020/AC-022 — linked campaign: pipeline_status_id stays NULL, no own product_lines row
// ---------------------------------------------------------------------------

it('create: linked to a project, without pipeline_status_id/product_lines -> 201, DB columns/rows empty (AC-020)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/campaigns', [
        'name' => 'Linked Campaign',
        'project_id' => $project->id,
        ...campaignStoreDates(),
    ])->assertCreated();

    $campaignId = $response->json('data.id');
    $this->assertDatabaseHas('campaigns', [
        'id' => $campaignId,
        'project_id' => $project->id,
        'pipeline_status_id' => null,
        // The project fills country_id by default (ProjectFactory) -> prohibited
        // and NULL on the campaign row (BR-5, spec 0027).
        'country_id' => null,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);

    // Spec 0094, D-1/D-2: a linked campaign owns NO product line of its own.
    expect(Campaign::find($campaignId)->productLines)->toBeEmpty();
});

it('create: linked to a project AND an explicit pipeline_status_id -> 422 (AC-022, BR-2)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create();
    $status = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Conflicting',
        'project_id' => $project->id,
        'pipeline_status_id' => $status->id,
    ])->assertStatus(422)->assertJsonValidationErrors('pipeline_status_id');

    expect(Campaign::count())->toBe(0);
});

// BR-5 geo refinement (spec 0027, AC-004/AC-005/AC-006) moved to
// CampaignGeoScopeTest.php (file-size split, engineering.md §6).

// ---------------------------------------------------------------------------
// AC-021 — GET linked campaign: derived_from_project + effective project values
// ---------------------------------------------------------------------------

it('show: a linked campaign reports derived_from_project=true with the PROJECT\'s values (AC-021)', function () {
    $actor = campaignUserWith(['view']);
    $status = PipelineStatus::factory()->create(['name' => 'Active', 'color' => '#00ff00']);
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Sales']);
    $state = State::factory()->create(['name' => 'Lazio']);
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id, 'name' => 'Widgets']);
    $project = Project::factory()->create([
        'pipeline_status_id' => $status->id,
        'state_id' => $state->id,
    ]);
    $project->productLines()->create(['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]);
    $campaign = Campaign::factory()->forProject($project)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/campaigns/{$campaign->id}")
        ->assertOk()
        ->assertJsonPath('data.derived_from_project', true)
        ->assertJsonPath('data.pipeline_status_id', $status->id)
        ->assertJsonPath('data.pipeline_status.name', 'Active')
        ->assertJsonPath('data.state_id', $state->id)
        ->assertJsonPath('data.product_lines.0.business_function.id', $businessFunction->id)
        ->assertJsonPath('data.product_lines.0.product_category.id', $category->id);
});

it('show: 403 without campaigns.view', function () {
    $actor = campaignUserWith([]);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/campaigns/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent campaign', function () {
    $actor = campaignUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/campaigns/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-023 — standalone campaign: the 3 BR-2 fields + country_id are required
// ---------------------------------------------------------------------------

it('create: standalone (project_id null) missing product_lines -> 422 on product_lines (AC-023)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    unset($fields['product_lines']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Incomplete Standalone'], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Campaign::count())->toBe(0);
});

it('create: standalone missing country_id -> 422 on country_id (AC-023, BR-4)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneClassificationFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'No Country'], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('country_id');

    expect(Campaign::count())->toBe(0);
});

it('create: standalone with pipeline_status_id/product_lines + country_id -> 201, derived_from_project=false (AC-023)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Full Standalone'], $fields))
        ->assertCreated()
        ->assertJsonPath('data.derived_from_project', false)
        ->assertJsonPath('data.pipeline_status_id', $fields['pipeline_status_id']);
});

it('create: 403 without campaigns.create', function () {
    $actor = campaignUserWith([]);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Nope'], $fields))->assertForbidden();

    expect(Campaign::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-024/AC-025/AC-027 — BR-3 budget guard on create
// ---------------------------------------------------------------------------

it('create: 422 with an explanatory message when the requested budget exceeds the residual (AC-024)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 600]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/campaigns', [
        'name' => 'Over Budget',
        'project_id' => $project->id,
        'total_budget' => 500,
        ...campaignStoreDates(),
    ])->assertStatus(422)->assertJsonValidationErrors('total_budget');

    $message = collect($response->json('errors.total_budget'))->first();
    expect($message)->toContain($project->code)
        ->and($message)->toContain('1000.00')
        ->and($message)->toContain('600.00')
        ->and($message)->toContain('400.00')
        ->and($message)->toContain('500.00');

    expect(Campaign::where('name', 'Over Budget')->exists())->toBeFalse();
    expect(Campaign::count())->toBe(1); // only the pre-existing one
});

it('create: 201 when the requested budget fits the residual exactly (AC-025)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 600]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Within Budget',
        'project_id' => $project->id,
        'total_budget' => 400,
        ...campaignStoreDates(),
    ])->assertCreated();
});

it('create: any total_budget is accepted when project.total_budget is NULL (AC-027)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create(['total_budget' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Unbounded',
        'project_id' => $project->id,
        'total_budget' => 999999,
        ...campaignStoreDates(),
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-026 — update: the campaign's own current budget doesn't double-count
// ---------------------------------------------------------------------------

it('update: the campaign\'s own budget is excluded from its allocated sum (AC-026)', function () {
    $actor = campaignUserWith(['update']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    $campaign = Campaign::factory()->forProject($project)->create(['total_budget' => 600]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['total_budget' => 1000])->assertOk();

    $this->patchJson("/api/campaigns/{$campaign->id}", ['total_budget' => 1001])
        ->assertStatus(422)->assertJsonValidationErrors('total_budget');
});

// ---------------------------------------------------------------------------
// AC-028 — update: standalone -> linked nulls pipeline_status_id and clears
// the campaign's own product_lines collection; geo follows BR-5 instead
// (spec 0027, D-3 — this is NOT the former blanket "4 derived columns"
// behaviour; spec 0094, D-1/D-2 rewrote the 2 remaining scalars into the
// collection — rewritten because the requirement changed, not test
// tampering).
// ---------------------------------------------------------------------------

it('update: setting project_id on a standalone campaign zeroes pipeline_status_id and clears its own product_lines, geo follows BR-5 (AC-028)', function () {
    $actor = campaignUserWith(['update']);
    // state_id explicitly null: the campaign's OWN country (its default
    // factory geo) has no state, so linking to a project with a DIFFERENT
    // country never produces an inconsistent merged tuple below. The default
    // factory already carries ONE coherent product line (spec 0094).
    $campaign = Campaign::factory()->create(['name' => 'Was Standalone', 'state_id' => null]);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['project_id' => $project->id])
        ->assertOk()
        ->assertJsonPath('data.derived_from_project', true);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'project_id' => $project->id,
        'pipeline_status_id' => null,
        // The project fills country_id by default (ProjectFactory) -> nulled
        // on the campaign row, defence in depth (BR-5).
        'country_id' => null,
    ]);

    expect($campaign->fresh()->productLines)->toBeEmpty();
});

it('update: 403 without campaigns.update', function () {
    $actor = campaignUserWith([]);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// operational_site_id tests (sede inheritance cascade) live in
// CampaignOperationalSiteTest.php (file-size split, engineering.md §6).
// ---------------------------------------------------------------------------
// delete — DELETE /api/campaigns/{campaign} (no delete-guard, unlike Projects)
// ---------------------------------------------------------------------------

it('delete: 204, removes the campaign', function () {
    $actor = campaignUserWith(['delete']);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/campaigns/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('campaigns', ['id' => $target->id]);
});

it('delete: 403 without campaigns.delete', function () {
    $actor = campaignUserWith([]);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/campaigns/{$target->id}")->assertForbidden();
});

<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
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

if (! function_exists('projectStoreExtras')) {
    /**
     * The create-only fields made required alongside name/pipeline_status/
     * country: `product_lines` (spec 0094 — REPLACING the former
     * business_function_id/product_category_id scalars, at least one row)
     * and the start/end planning dates. Spread into a store payload to
     * satisfy the required rules without repeating the fixture setup in
     * every test.
     *
     * @return array<string, mixed>
     */
    function projectStoreExtras(): array
    {
        return [
            'product_lines' => [projectCoherentProductLine()],
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
    }
}

if (! function_exists('projectCoherentProductLine')) {
    /**
     * One coherent {business_function_id, product_category_id} row (spec
     * 0023 REV pairing rule): the category is created UNDER the business
     * function, so its effective business function matches — the write-side
     * coherence rule (ProductLineSetValidator) rejects a mismatched pair.
     *
     * @return array{business_function_id: int, product_category_id: int}
     */
    function projectCoherentProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'business_function_id' => $businessFunction->id,
            'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
        ];
    }
}

// `code` generation/manual-override tests (AC-010/AC-011, spec 0025) live in
// ProjectCodeGenerationTest.php (file-size split, engineering.md §6).

// ---------------------------------------------------------------------------
// create — country_id (AC-001/AC-002/AC-003, spec 0027 BR-4)
// ---------------------------------------------------------------------------

it('create: without country_id -> 422 (AC-001)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'No Country', 'pipeline_status_id' => $status->id])
        ->assertStatus(422)->assertJsonValidationErrors('country_id');

    expect(Project::count())->toBe(0);
});

it('create: with country_id only -> 201, geo_scope=country (AC-001, D-2)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $country = Country::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'Country Scoped', 'pipeline_status_id' => $status->id, 'country_id' => $country->id, ...projectStoreExtras()])
        ->assertCreated()
        ->assertJsonPath('data.geo_scope', 'country')
        ->assertJsonPath('data.country_id', $country->id);
});

it('create: a state_id that does not belong to country_id -> 422 on state_id (AC-002, BR-4)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $geo = geoChain();
    $otherCountry = Country::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Inconsistent Geo',
        'pipeline_status_id' => $status->id,
        'country_id' => $otherCountry->id,
        'state_id' => $geo['state']->id,
    ])->assertStatus(422)->assertJsonValidationErrors('state_id');

    expect(Project::count())->toBe(0);
});

it('create: a full consistent geo chain -> 201, geo_scope=city (AC-003, D-2)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $geo = geoChain();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'City Scoped',
        'pipeline_status_id' => $status->id,
        'country_id' => $geo['country']->id,
        'state_id' => $geo['state']->id,
        'province_id' => $geo['province']->id,
        'city_id' => $geo['city']->id,
        ...projectStoreExtras(),
    ])->assertCreated()
        ->assertJsonPath('data.geo_scope', 'city')
        ->assertJsonPath('data.city.name', 'Milano');
});

it('create: 422 when name is missing (AC-012)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['pipeline_status_id' => $status->id])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: missing pipeline_status_id -> 201, falls back to the system_key=new status (spec 0039 D-3, AC-009)', function () {
    // requirement changed (spec 0039, D-3): pipeline_status_id went from
    // `required` to `nullable` — an omitted FK no longer 422s, it falls
    // back to the mandatory system_key='new' status (AC-012 is superseded).
    $actor = projectUserWith(['create']);
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'No Status', 'country_id' => $countryId, ...projectStoreExtras()])
        ->assertCreated();

    $project = Project::query()->with('pipelineStatus')->where('name', 'No Status')->sole();
    expect($project->pipelineStatus->system_key)->toBe('new');
});

it('create: 422 when end_date < start_date (AC-013)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Bad Dates',
        'pipeline_status_id' => $status->id,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-01',
    ])->assertStatus(422)->assertJsonValidationErrors('end_date');

    expect(Project::count())->toBe(0);
});

it('create: 422 when a mandatory field is missing (product_lines/start_date)', function (string $missing) {
    $actor = projectUserWith(['create']);
    Sanctum::actingAs($actor);

    $payload = [
        'name' => 'Missing '.$missing,
        'pipeline_status_id' => PipelineStatus::factory()->create()->id,
        'country_id' => Country::factory()->create()->id,
        ...projectStoreExtras(),
    ];
    unset($payload[$missing]);

    $this->postJson('/api/projects', $payload)
        ->assertStatus(422)->assertJsonValidationErrors($missing);

    expect(Project::count())->toBe(0);
})->with(['product_lines', 'start_date']);

it('create: succeeds when end_date is omitted (optional)', function () {
    $actor = projectUserWith(['create']);
    Sanctum::actingAs($actor);

    $payload = [
        'name' => 'No End Date',
        'pipeline_status_id' => PipelineStatus::factory()->create()->id,
        'country_id' => Country::factory()->create()->id,
        ...projectStoreExtras(),
    ];
    unset($payload['end_date']);

    $this->postJson('/api/projects', $payload)->assertCreated();

    expect(Project::query()->where('name', 'No End Date')->sole()->end_date)->toBeNull();
});

// operational_site_id tests (sede inheritance cascade) live in
// ProjectOperationalSiteTest.php (file-size split, engineering.md §6).

it('create: 403 without projects.create, no row persisted', function () {
    $actor = projectUserWith([]);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    // Payload is otherwise valid so validation passes and the request reaches
    // the controller's authorize('create') — which is what returns 403.
    $this->postJson('/api/projects', ['name' => 'Nope', 'pipeline_status_id' => $status->id, 'country_id' => $countryId, ...projectStoreExtras()])->assertForbidden();

    expect(Project::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// show — GET /api/projects/{project} (AC-014)
// ---------------------------------------------------------------------------

it('show: exposes allocated_budget and remaining_budget computed from campaigns (AC-014)', function () {
    $actor = projectUserWith(['view']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 300]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 500]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.allocated_budget', '800.00')
        ->assertJsonPath('data.remaining_budget', '200.00')
        ->assertJsonPath('data.campaigns_count', 2);
});

it('show: remaining_budget is null when total_budget is null', function () {
    $actor = projectUserWith(['view']);
    $project = Project::factory()->create(['total_budget' => null]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.total_budget', null)
        ->assertJsonPath('data.remaining_budget', null)
        ->assertJsonPath('data.allocated_budget', '0.00');
});

it('show: 403 without projects.view', function () {
    $actor = projectUserWith([]);
    $target = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/projects/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent project', function () {
    $actor = projectUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/projects/{project} (AC-015)
// ---------------------------------------------------------------------------

it('update: lowering total_budget below the allocated sum is NEVER blocked, remaining goes negative (AC-015)', function () {
    $actor = projectUserWith(['update']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 800]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['total_budget' => 500])
        ->assertOk()
        ->assertJsonPath('data.total_budget', '500.00')
        ->assertJsonPath('data.remaining_budget', '-300.00');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'total_budget' => 500.00]);
});

it('update: 403 without projects.update', function () {
    $actor = projectUserWith([]);
    $target = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/projects/{project} (AC-016)
// ---------------------------------------------------------------------------

it('delete: 409 when the project has at least one campaign (AC-016)', function () {
    $actor = projectUserWith(['delete']);
    $project = Project::factory()->create();
    Campaign::factory()->forProject($project)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/projects/{$project->id}")->assertStatus(409);

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});

it('delete: 204 when the project has no campaigns (AC-016)', function () {
    $actor = projectUserWith(['delete']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

it('delete: 403 without projects.delete', function () {
    $actor = projectUserWith([]);
    $target = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/projects/{$target->id}")->assertForbidden();
});

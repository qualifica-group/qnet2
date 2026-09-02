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

// `code` (PRJ-0001..., spec 0025, BR-1): manual-on-create with a sequential
// server-side fallback, plus the GET /api/projects/next-code auto-fill
// suggestion. Extracted out of ProjectCrudTest.php (file-size split,
// engineering.md §6).

if (! function_exists('projectUserWith')) {
    /**
     * Local copy mirroring ProjectCrudTest's (each test file guards its own,
     * since file load order across the suite is not guaranteed).
     *
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

if (! function_exists('projectCoherentProductLine')) {
    /**
     * Local copy mirroring ProjectCrudTest's.
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

if (! function_exists('projectStoreExtras')) {
    /**
     * Local copy mirroring ProjectCrudTest's.
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

it('create: code is server-generated PRJ-0001, then PRJ-0002 (AC-010)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'First', 'pipeline_status_id' => $status->id, 'country_id' => $countryId, ...projectStoreExtras()])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRJ-0001');

    $this->postJson('/api/projects', ['name' => 'Second', 'pipeline_status_id' => $status->id, 'country_id' => $countryId, ...projectStoreExtras()])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRJ-0002');
});

// spec 0025 changed the requirement: `code` is now manual-on-create (AC-002),
// no longer server-only — the former "explicit code is ignored" behavior
// (AC-011 pre-0025) is replaced by these tests.

it('create: no `code` in the payload -> code is server-generated PRJ-0001 (AC-001)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'No Code', 'pipeline_status_id' => $status->id, 'country_id' => $countryId, ...projectStoreExtras()])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRJ-0001');
});

it('create: an explicit `code` in the payload is persisted as-is (AC-002)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Manual Code',
        'pipeline_status_id' => $status->id,
        'country_id' => $countryId,
        'code' => 'ACME-2026',
        ...projectStoreExtras(),
    ])->assertCreated()->assertJsonPath('data.code', 'ACME-2026');

    $this->assertDatabaseHas('projects', ['code' => 'ACME-2026']);
});

it('create: `code` as an empty string -> code is server-generated (AC-003)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Empty Code',
        'pipeline_status_id' => $status->id,
        'country_id' => $countryId,
        'code' => '',
        ...projectStoreExtras(),
    ])->assertCreated()->assertJsonPath('data.code', 'PRJ-0001');
});

it('create: a duplicate `code` -> 422 on the `code` field (AC-004)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Project::factory()->create(['code' => 'ACME-2026']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Duplicate Code',
        'pipeline_status_id' => $status->id,
        'country_id' => $countryId,
        'code' => 'ACME-2026',
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});

it('create: a `code` of 33+ characters -> 422 (AC-005)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Too Long',
        'pipeline_status_id' => $status->id,
        'country_id' => $countryId,
        'code' => str_repeat('A', 33),
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});

it('create: a manual non-PRJ code does not break the sequential generator (AC-006)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Manual First',
        'pipeline_status_id' => $status->id,
        'country_id' => $countryId,
        'code' => 'ACME-2026',
        ...projectStoreExtras(),
    ])->assertCreated()->assertJsonPath('data.code', 'ACME-2026');

    $this->postJson('/api/projects', ['name' => 'Generated Second', 'pipeline_status_id' => $status->id, 'country_id' => $countryId, ...projectStoreExtras()])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRJ-0001');
});

it('update: a `code` different from the persisted one -> 422, code unchanged (AC-007)', function () {
    $actor = projectUserWith(['update']);
    $project = Project::factory()->create(['code' => 'PRJ-0001']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['code' => 'PRJ-9999'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'code' => 'PRJ-0001']);
});

it('update: resubmitting the SAME persisted `code` is a no-op, not rejected', function () {
    $actor = projectUserWith(['update']);
    $project = Project::factory()->create(['code' => 'PRJ-0001']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['code' => 'PRJ-0001', 'name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.code', 'PRJ-0001')
        ->assertJsonPath('data.name', 'Renamed');
});

it('meta: permissions.fields.code is editable in create and readonly in update (AC-009)', function () {
    $actor = projectUserWith(['viewAny', 'create', 'view', 'update']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/projects')
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', true)
        ->assertJsonPath('permissions.fields.code.readonly', false);

    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', false)
        ->assertJsonPath('permissions.fields.code.readonly', true);
});

// ---------------------------------------------------------------------------
// next-code — GET /api/projects/next-code (spec 0025, auto-fill suggestion)
// ---------------------------------------------------------------------------

it('next-code: suggests PRJ-0001 on an empty table, then the following sequence', function () {
    $actor = projectUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects/next-code')
        ->assertOk()->assertJsonPath('data.code', 'PRJ-0001');

    Project::factory()->create(['code' => 'PRJ-0007']);

    $this->getJson('/api/projects/next-code')
        ->assertOk()->assertJsonPath('data.code', 'PRJ-0008');
});

it('next-code: 403 without projects.create', function () {
    Sanctum::actingAs(projectUserWith(['viewAny']));

    $this->getJson('/api/projects/next-code')->assertForbidden();
});

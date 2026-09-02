<?php

use App\Models\BusinessFunction;
use App\Models\Country;
use App\Models\OperationalSite;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// `operational_site_id` (sede inheritance cascade project -> campaign -> lead):
// a PREFILL MODIFIABLE field, never a read-through — no server-side
// inheritance/lock, freely editable at every level. Extracted out of
// ProjectCrudTest.php (file-size split, engineering.md §6).

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

if (! function_exists('projectStoreExtras')) {
    /**
     * Local copy mirroring ProjectCrudTest's create-only required fields.
     *
     * @return array<string, mixed>
     */
    function projectStoreExtras(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
    }
}

it('create: persists operational_site_id and exposes the composed operational_site label', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/projects', [
        'name' => 'With Sede',
        'pipeline_status_id' => $status->id,
        'country_id' => $countryId,
        'operational_site_id' => $site->id,
        ...projectStoreExtras(),
    ])->assertCreated()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.operational_site.label', 'Via Roma 1');

    $this->assertDatabaseHas('projects', ['id' => $response->json('data.id'), 'operational_site_id' => $site->id]);
});

it('create: a non-existent operational_site_id -> 422 (no row persisted)', function () {
    $actor = projectUserWith(['create']);
    $status = PipelineStatus::factory()->create();
    $countryId = Country::factory()->create()->id;
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Bad Sede',
        'pipeline_status_id' => $status->id,
        'country_id' => $countryId,
        'operational_site_id' => 999999,
        ...projectStoreExtras(),
    ])->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    expect(Project::count())->toBe(0);
});

it('update: sets operational_site_id on a project that had none, no server-side forcing', function () {
    $actor = projectUserWith(['update']);
    $project = Project::factory()->create(['operational_site_id' => null]);
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['operational_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id);

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'operational_site_id' => $site->id]);
});

it('for-select: meta.operational_site is {id, label}', function () {
    $actor = projectUserWith(['viewAny']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Verdi 5', 'is_primary' => true]);
    $project = Project::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/projects/for-select')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $project->id);

    expect($item['meta']['operational_site'])->toMatchArray(['id' => $site->id, 'label' => 'Via Verdi 5']);
});

it('for-select: meta.operational_site is null when the project has no sede', function () {
    $actor = projectUserWith(['viewAny']);
    $project = Project::factory()->create(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/projects/for-select')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $project->id);

    expect($item['meta']['operational_site'])->toBeNull();
});

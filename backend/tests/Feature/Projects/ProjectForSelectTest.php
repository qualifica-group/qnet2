<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\PipelineStatus;
use App\Models\Project;
use App\Models\Referent;
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
// AC-017 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/projects/for-select')->assertUnauthorized();
});

it('allows actors without projects.viewAny (200 — ADR 0011 amended)', function () {
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects/for-select')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-017 — item shape: label "PRJ-0001 — Denominazione" + full meta block
// ---------------------------------------------------------------------------

it('maps a project to label "{code} — {name}" with the full campaign-form meta block (AC-017)', function () {
    $actor = projectUserWith(['viewAny']);
    $status = PipelineStatus::factory()->create(['name' => 'Attivo']);
    $partner = Referent::factory()->create(['name' => 'Ada Partner']);
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Marketing']);
    $project = Project::factory()->create([
        'pipeline_status_id' => $status->id,
        'partner_id' => $partner->id,
        'business_function_id' => $businessFunction->id,
        'total_budget' => 1000,
    ]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 400]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/projects/for-select')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $project->id);

    expect($item['label'])->toBe(sprintf('%s — %s', $project->code, $project->name))
        ->and($item['meta']['pipeline_status'])->toMatchArray(['id' => $status->id, 'label' => 'Attivo'])
        ->and($item['meta']['partner'])->toMatchArray(['id' => $partner->id, 'label' => 'Ada Partner'])
        ->and($item['meta']['business_function'])->toMatchArray(['id' => $businessFunction->id, 'label' => 'Marketing'])
        ->and($item['meta']['total_budget'])->toBe('1000.00')
        ->and($item['meta']['allocated_budget'])->toBe('400.00')
        ->and($item['meta']['remaining_budget'])->toBe('600.00');
});

it('meta fields are null when the corresponding relation is unset', function () {
    $actor = projectUserWith(['viewAny']);
    $project = Project::factory()->create([
        'partner_id' => null,
        'business_function_id' => null,
        'state_id' => null,
        'product_category_id' => null,
        'total_budget' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/projects/for-select')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $project->id);

    expect($item['meta']['partner'])->toBeNull()
        ->and($item['meta']['business_function'])->toBeNull()
        ->and($item['meta']['state'])->toBeNull()
        ->and($item['meta']['product_category'])->toBeNull()
        ->and($item['meta']['total_budget'])->toBeNull()
        ->and($item['meta']['remaining_budget'])->toBeNull();
});

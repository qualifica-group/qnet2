<?php

use App\Models\PipelineStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('pipelineStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function pipelineStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("pipeline-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("pipeline-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/pipeline-statuses/for-select')->assertUnauthorized();
});

it('allows actors without pipeline-statuses.viewAny (200 — ADR 0011 amended)', function () {
    $actor = pipelineStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/pipeline-statuses/for-select')->assertOk();
});

it('allows actors with pipeline-statuses.viewAny (200) and returns the paginated envelope', function () {
    $actor = pipelineStatusUserWith(['viewAny']);
    PipelineStatus::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/pipeline-statuses/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-006 — item shape + search
// ---------------------------------------------------------------------------

it('maps a project status to { id, label: name, meta: { system_key } } (spec 0039, D-2)', function () {
    $actor = pipelineStatusUserWith(['viewAny']);
    $target = PipelineStatus::factory()->create(['name' => 'In Progress']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/pipeline-statuses/for-select?search=In Progress')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    // requirement changed (spec 0039, AC-009): `meta.system_key` is a new,
    // always-present key (null for a custom row) — was {id, label} only.
    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'In Progress', 'meta' => ['system_key' => null]])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta']);
});

it('search="boz" returns only names containing "boz" (AC-006)', function () {
    $actor = pipelineStatusUserWith(['viewAny']);
    $match = PipelineStatus::factory()->create(['name' => 'Bozza']);
    PipelineStatus::factory()->create(['name' => 'Completato']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/pipeline-statuses/for-select?search=boz')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-006 — ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = pipelineStatusUserWith(['viewAny']);
    $searchMatch = PipelineStatus::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = PipelineStatus::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/pipeline-statuses/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = pipelineStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/pipeline-statuses/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\PipelineStatus;
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
// GET /api/projects — AC-001/AC-002/AC-003/AC-004/AC-005
// ---------------------------------------------------------------------------

it('index: 403 without projects.viewAny (AC-003)', function () {
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects')->assertForbidden();
});

it('summary: 403 without projects.viewAny (AC-003)', function () {
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects/summary')->assertForbidden();
});

it('index: paginates offset/limit, ordered by created_at desc (AC-001)', function () {
    $actor = projectUserWith(['viewAny']);
    $older = Project::factory()->create(['created_at' => now()->subDay()]);
    $newer = Project::factory()->create(['created_at' => now()]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/projects?limit=1&offset=0')->assertOk();

    expect($response->json('items'))->toHaveCount(1)
        ->and($response->json('items.0.id'))->toBe($newer->id)
        ->and($response->json('pagination'))->toMatchArray(['total' => 2, 'offset' => 0, 'limit' => 1, 'total_pages' => 2]);

    $this->getJson('/api/projects?limit=1&offset=1')
        ->assertOk()
        ->assertJsonPath('items.0.id', $older->id);
});

it('index: campaigns_count/leads_count are correct (AC-001)', function () {
    $actor = projectUserWith(['viewAny']);
    $project = Project::factory()->create();
    $campaign = Campaign::factory()->forProject($project)->create();
    Lead::factory()->count(4)->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $item = collect($this->getJson('/api/projects')->assertOk()->json('items'))->firstWhere('id', $project->id);

    expect($item['campaigns_count'])->toBe(1)
        ->and($item['leads_count'])->toBe(4);
});

it('index: a project with 0 leads exposes leads_count 0 (AC-004)', function () {
    $actor = projectUserWith(['viewAny']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $item = collect($this->getJson('/api/projects')->assertOk()->json('items'))->firstWhere('id', $project->id);

    expect($item['leads_count'])->toBe(0);
});

it('index: search matches code OR name (AC-002)', function () {
    $actor = projectUserWith(['viewAny']);
    $matchByName = Project::factory()->create(['name' => 'Spring Outreach']);
    $matchByCode = Project::factory()->create(['code' => 'PRJ-0999']);
    Project::factory()->create(['name' => 'Unrelated', 'code' => 'PRJ-0001']);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson('/api/projects?search=Spring')->assertOk()->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matchByName->id]);

    $ids = collect($this->getJson('/api/projects?search=PRJ-0999')->assertOk()->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matchByCode->id]);
});

it('index: pipeline_status_id filters by status (AC-002)', function () {
    $actor = projectUserWith(['viewAny']);
    $status = PipelineStatus::factory()->create();
    $matching = Project::factory()->create(['pipeline_status_id' => $status->id]);
    Project::factory()->create();
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson("/api/projects?pipeline_status_id={$status->id}")->assertOk()->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id]);
});

it('index: advancedFilters[budget_range] filters card-grid rows (AC-018)', function () {
    $actor = projectUserWith(['viewAny']);
    $matching = Project::factory()->create(['total_budget' => 5000]);
    Project::factory()->create(['total_budget' => 50]);
    Sanctum::actingAs($actor);

    $query = http_build_query(['advancedFilters' => ['budget_range' => ['from' => 1000, 'to' => 10000]]]);

    $ids = collect($this->getJson("/api/projects?{$query}")->assertOk()->json('items'))->pluck('id');

    expect($ids->all())->toBe([$matching->id]);
});

it('index: advancedFilters with a key outside the allow-list returns 422 (AC-018)', function () {
    $actor = projectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $query = http_build_query(['advancedFilters' => ['not_a_real_filter' => 'x']]);

    $this->getJson("/api/projects?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['advancedFilters.not_a_real_filter']);
});

it('index: advancedFilters is authorized the same as the plain index — 403 without projects.viewAny (AC-018)', function () {
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);

    $query = http_build_query(['advancedFilters' => ['budget_range' => ['from' => 1000]]]);

    $this->getJson("/api/projects?{$query}")->assertForbidden();
});

it('index: can.update/can.delete reflect the real Gate result per user (AC-005)', function () {
    $withUpdate = projectUserWith(['viewAny', 'update']);
    $project = Project::factory()->create();
    Sanctum::actingAs($withUpdate);

    $item = collect($this->getJson('/api/projects')->assertOk()->json('items'))->firstWhere('id', $project->id);
    expect($item['can'])->toMatchArray(['update' => true, 'delete' => false]);

    $withDelete = projectUserWith(['viewAny', 'delete']);
    Sanctum::actingAs($withDelete);

    $item = collect($this->getJson('/api/projects')->assertOk()->json('items'))->firstWhere('id', $project->id);
    expect($item['can'])->toMatchArray(['update' => false, 'delete' => true]);
});

// ---------------------------------------------------------------------------
// GET /api/projects/summary
// ---------------------------------------------------------------------------

it('summary: aggregates projects/campaigns/leads reachable through a project (AC-004)', function () {
    $actor = projectUserWith(['viewAny']);
    $project = Project::factory()->create();
    $linkedCampaign = Campaign::factory()->forProject($project)->create();
    Campaign::factory()->create(); // standalone, not counted
    Lead::factory()->count(2)->create(['campaign_id' => $linkedCampaign->id]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects/summary')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.projects_count', 1)
        ->assertJsonPath('data.campaigns_count', 1)
        ->assertJsonPath('data.leads_count', 2);
});

it('summary: leads_count is 0 when there are no leads', function () {
    $actor = projectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects/summary')
        ->assertOk()
        ->assertJsonPath('data.leads_count', 0);
});

it('advanced filters: product_category forwards source.params.include_inactive (AC-017)', function () {
    Sanctum::actingAs(projectUserWith(['viewAny']));

    $filters = collect($this->getJson('/api/tables/projects/columns')->assertOk()->json('data.advancedFilters'))
        ->keyBy('name');

    expect($filters['product_category']['source'])->toBe([
        'resource' => 'product-categories',
        'params' => ['include_inactive' => 1],
    ]);
});

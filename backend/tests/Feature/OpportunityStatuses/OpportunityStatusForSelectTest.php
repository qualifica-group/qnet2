<?php

use App\Models\OpportunityStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunity-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunity-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/opportunity-statuses/for-select')->assertUnauthorized();
});

it('allows actors without opportunity-statuses.viewAny (200 — ADR 0011 amended)', function () {
    $actor = opportunityStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunity-statuses/for-select')->assertOk();
});

it('allows actors with opportunity-statuses.viewAny (200) and returns the paginated envelope', function () {
    $actor = opportunityStatusUserWith(['viewAny']);
    OpportunityStatus::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunity-statuses/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-008/BR-7 — item shape + sort_order-first ordering + search
// ---------------------------------------------------------------------------

it('maps an opportunity status to { id, label: name, meta: { system_key } } (BR-7)', function () {
    $actor = opportunityStatusUserWith(['viewAny']);
    $target = OpportunityStatus::factory()->create(['name' => 'In Progress']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/opportunity-statuses/for-select?search=In Progress')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'In Progress', 'meta' => ['system_key' => null]])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta']);
});

it('orders by sort_order asc, not alphabetically (BR-7)', function () {
    $actor = opportunityStatusUserWith(['viewAny']);
    // sort_order 100/101 (not 0/10/20) so this pair never collides with the
    // three migration-seeded system rows.
    $zLast = OpportunityStatus::factory()->create(['name' => 'Zeta', 'sort_order' => 100]);
    $aFirst = OpportunityStatus::factory()->create(['name' => 'Alpha', 'sort_order' => 101]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/opportunity-statuses/for-select')->assertOk();
    $ids = collect($response->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, [$zLast->id, $aFirst->id], true))
        ->values()
        ->all();

    expect($ids)->toBe([$zLast->id, $aFirst->id]);
});

it('search="trat" returns only names containing "trat" (BR-7)', function () {
    $actor = opportunityStatusUserWith(['viewAny']);
    $match = OpportunityStatus::factory()->create(['name' => 'Trattativa']);
    OpportunityStatus::factory()->create(['name' => 'Chiusa']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/opportunity-statuses/for-select?search=trat')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-008 — ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = opportunityStatusUserWith(['viewAny']);
    $searchMatch = OpportunityStatus::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = OpportunityStatus::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunity-statuses/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = opportunityStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunity-statuses/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

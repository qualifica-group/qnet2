<?php

use App\Models\RewardStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/reward-statuses/for-select')->assertUnauthorized();
});

it('allows actors without reward-statuses.viewAny (200 — ADR 0011 amended)', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-statuses/for-select')->assertOk();
});

it('allows actors with reward-statuses.viewAny (200) and returns the paginated envelope', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    RewardStatus::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-statuses/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// BR-5/AC-009 — active-only, sort_order-first ordering, search
// ---------------------------------------------------------------------------

it('maps a reward status to { id, label: name, meta: { system_key } } (AC-009)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    $target = RewardStatus::factory()->create(['name' => 'In Progress']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reward-statuses/for-select?search=In Progress')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'In Progress', 'meta' => ['system_key' => null]])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta']);
});

it('excludes inactive statuses (BR-5, AC-009)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    $active = RewardStatus::factory()->create(['name' => 'Active One', 'is_active' => true]);
    $inactive = RewardStatus::factory()->create(['name' => 'Inactive One', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reward-statuses/for-select')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('orders by sort_order asc, not alphabetically (BR-5, AC-009)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    $zLast = RewardStatus::factory()->create(['name' => 'Zeta', 'sort_order' => 100]);
    $aFirst = RewardStatus::factory()->create(['name' => 'Alpha', 'sort_order' => 101]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reward-statuses/for-select')->assertOk();
    $ids = collect($response->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, [$zLast->id, $aFirst->id], true))
        ->values()
        ->all();

    expect($ids)->toBe([$zLast->id, $aFirst->id]);
});

it('search="appr" returns only names containing "appr" (AC-009)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    $match = RewardStatus::factory()->create(['name' => 'Approvato']);
    RewardStatus::factory()->create(['name' => 'Consegnato']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reward-statuses/for-select?search=appr')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-009 — ids[] hydration even for inactive/off-page, pagination cap
// ---------------------------------------------------------------------------

it('hydrates ids[] EVEN when the status is inactive (D-7, BR-5, AC-009)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    $inactive = RewardStatus::factory()->create(['name' => 'Deactivated One', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/reward-statuses/for-select?ids[]={$inactive->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($inactive->id);
});

it('appends ids[] even when filtered out by search and does NOT inflate total (AC-009)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    $searchMatch = RewardStatus::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = RewardStatus::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/reward-statuses/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422, AC-009)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-statuses/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

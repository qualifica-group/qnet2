<?php

use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-types.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// auth + authorization (AC-007)
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/reward-types/for-select')->assertUnauthorized();
});

it('allows actors without reward-types.viewAny (200 — ADR 0011 amended)', function () {
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-types/for-select')->assertOk();
});

it('allows actors with reward-types.viewAny (200) and returns the paginated envelope', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    RewardType::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-types/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-009 — item shape + name-asc ordering + search
// ---------------------------------------------------------------------------

it('maps a reward type to { id, label: name } (AC-009)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    $target = RewardType::factory()->create(['name' => 'Buono Amazon']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reward-types/for-select?search=Buono Amazon')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Buono Amazon'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('orders items by name asc (BR-4, AC-009)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    $zLast = RewardType::factory()->create(['name' => 'Zeta reward']);
    $aFirst = RewardType::factory()->create(['name' => 'Alpha reward']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reward-types/for-select')->assertOk();
    $ids = collect($response->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, [$zLast->id, $aFirst->id], true))
        ->values()
        ->all();

    expect($ids)->toBe([$aFirst->id, $zLast->id]);
});

it('search="amaz" returns only names containing "amaz" (AC-009)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    $match = RewardType::factory()->create(['name' => 'Buono Amazon']);
    RewardType::factory()->create(['name' => 'Buono carburante']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reward-types/for-select?search=amaz')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-009 — ids[] hydration outside the current page + limit cap
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total (AC-009)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    $searchMatch = RewardType::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = RewardType::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/reward-types/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422, AC-009)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-types/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

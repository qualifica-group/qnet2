<?php

use App\Models\RewardType;
use App\Models\User;
use Database\Seeders\DemoRewardTypeSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
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
// create — POST /api/reward-types (AC-001)
// ---------------------------------------------------------------------------

it('create: 201 + persists + returns {id,name,color,created_at,updated_at} + permissions (AC-001)', function () {
    $actor = rewardTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/reward-types', ['name' => 'Buono Amazon', 'color' => 'green'])
        ->assertCreated()
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'name', 'color', 'created_at', 'updated_at'], 'permissions'])
        ->assertJsonPath('data.name', 'Buono Amazon')
        ->assertJsonPath('data.color', 'green');

    $this->assertDatabaseHas('reward_types', ['id' => $response->json('data.id'), 'name' => 'Buono Amazon', 'color' => 'green']);
});

it('create: 422 when name is missing', function () {
    $actor = rewardTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-types', ['color' => 'green'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// create — BR-1 unique name (AC-002)
// ---------------------------------------------------------------------------

it('create: 422 when name duplicates an existing reward type, not 500 not 201 (AC-002)', function () {
    $actor = rewardTypeUserWith(['create']);
    RewardType::factory()->create(['name' => 'Buono Amazon']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-types', ['name' => 'Buono Amazon', 'color' => 'blue'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(RewardType::where('name', 'Buono Amazon')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-004 — color mandatory, missing/null/'' rejected on both POST and PATCH
// ---------------------------------------------------------------------------

it('create: 422 when color is missing (AC-004)', function () {
    $actor = rewardTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-types', ['name' => 'Buono Amazon'])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

it('create: 422 when color is null (AC-004)', function () {
    $actor = rewardTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-types', ['name' => 'Buono Amazon', 'color' => null])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

it('create: 422 when color is an empty string (AC-004)', function () {
    $actor = rewardTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-types', ['name' => 'Buono Amazon', 'color' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

it('update: 422 when color is explicitly sent as null (AC-004)', function () {
    $actor = rewardTypeUserWith(['update']);
    $target = RewardType::factory()->create(['color' => 'blue']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['color' => null])
        ->assertStatus(422)->assertJsonValidationErrors('color');

    $this->assertDatabaseHas('reward_types', ['id' => $target->id, 'color' => 'blue']);
});

// ---------------------------------------------------------------------------
// show — GET /api/reward-types/{rewardType}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = rewardTypeUserWith(['view']);
    $target = RewardType::factory()->create(['name' => 'Buono pasto', 'color' => 'amber']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/reward-types/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Buono pasto')
        ->assertJsonPath('data.color', 'amber');
});

it('show: 404 for a non-existent reward type', function () {
    $actor = rewardTypeUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-types/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/reward-types/{rewardType}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the reward type', function () {
    $actor = rewardTypeUserWith(['update']);
    $target = RewardType::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('reward_types', ['id' => $target->id, 'name' => 'After']);
});

it('update: 200 when re-submitting its OWN unchanged name (unique ignores self) (AC-003)', function () {
    $actor = rewardTypeUserWith(['update']);
    $target = RewardType::factory()->create(['name' => 'Same', 'color' => 'teal']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['name' => 'Same', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Same')
        ->assertJsonPath('data.color', 'teal');
});

it('update: 422 when name duplicates ANOTHER existing reward type', function () {
    $actor = rewardTypeUserWith(['update']);
    RewardType::factory()->create(['name' => 'Taken']);
    $target = RewardType::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// AC-005 — PATCH {name} only leaves color untouched (no implicit reset)
// ---------------------------------------------------------------------------

it('update: PATCH with only {name} leaves color untouched (AC-005)', function () {
    $actor = rewardTypeUserWith(['update']);
    $target = RewardType::factory()->create(['name' => 'Before', 'color' => 'violet']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After')
        ->assertJsonPath('data.color', 'violet');

    $this->assertDatabaseHas('reward_types', ['id' => $target->id, 'name' => 'After', 'color' => 'violet']);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/reward-types/{rewardType} (AC-006)
// ---------------------------------------------------------------------------

it('delete: 204 + removed from the DB (AC-006)', function () {
    $actor = rewardTypeUserWith(['delete']);
    $target = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-types/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('reward_types', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// AC-026 — clean seed creates no reward types; DemoRewardTypeSeeder is
// idempotent (updateOrCreate keyed by name)
// ---------------------------------------------------------------------------

it('the clean seed creates zero reward types (AC-026)', function () {
    // DatabaseSeeder::run() calls locations:add + RolePermissionSeeder +
    // DemoUserSeeder (no Demo*Seeder for reward-types). locations:add
    // imports the full geography dataset and is unrelated to this
    // assertion, so only the two seeders that could plausibly touch
    // `reward_types` are run directly, keeping this test fast.
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoUserSeeder::class);

    expect(RewardType::count())->toBe(0);
});

it('DemoRewardTypeSeeder run twice leaves the same row count (idempotent, AC-026)', function () {
    $this->seed(DemoRewardTypeSeeder::class);
    $firstRunCount = RewardType::count();

    $this->seed(DemoRewardTypeSeeder::class);

    expect(RewardType::count())->toBe($firstRunCount)
        ->and($firstRunCount)->toBeGreaterThan(0);
});

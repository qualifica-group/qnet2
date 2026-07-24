<?php

use App\Models\Reward;
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
// create — POST /api/reward-statuses (AC-001)
// ---------------------------------------------------------------------------

it('create: 201 + persists, sort_order assigned, system_key null, is_active true (AC-001)', function () {
    $actor = rewardStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses', ['name' => 'Approvato', 'color' => 'green'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Approvato')
        ->assertJsonPath('data.color', 'green')
        ->assertJsonPath('data.system_key', null)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data' => ['id', 'name', 'description', 'color', 'sort_order', 'is_active', 'system_key', 'created_at', 'updated_at'], 'permissions']);

    $this->assertDatabaseHas('reward_statuses', ['name' => 'Approvato', 'color' => 'green', 'system_key' => null]);
});

it('create: submitted sort_order/system_key are ignored (AC-001)', function () {
    $actor = rewardStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/reward-statuses', [
        'name' => 'Consegnato', 'color' => 'blue', 'sort_order' => 999, 'system_key' => 'hacked',
    ])->assertCreated();

    expect($response->json('data.sort_order'))->not->toBe(999)
        ->and($response->json('data.system_key'))->toBeNull();
});

// ---------------------------------------------------------------------------
// BR-1 — unique name (AC-002)
// ---------------------------------------------------------------------------

it('create: 422 when name duplicates an existing status, no row created (BR-1, AC-002)', function () {
    $actor = rewardStatusUserWith(['create']);
    RewardStatus::factory()->create(['name' => 'Approvato']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses', ['name' => 'Approvato', 'color' => 'blue'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(RewardStatus::where('name', 'Approvato')->count())->toBe(1);
});

it('update: 200 when re-submitting its OWN unchanged name (unique ignores self) (AC-002)', function () {
    $actor = rewardStatusUserWith(['update']);
    $target = RewardStatus::factory()->create(['name' => 'Same']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['name' => 'Same'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Same');
});

it('update: 422 when name duplicates ANOTHER existing status', function () {
    $actor = rewardStatusUserWith(['update']);
    RewardStatus::factory()->create(['name' => 'Taken']);
    $target = RewardStatus::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// BR-2 — mandatory color, optional description (AC-003)
// ---------------------------------------------------------------------------

it('create: 422 when color is missing (BR-2, AC-003)', function () {
    $actor = rewardStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses', ['name' => 'Nope'])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

it('create: 422 when color is null or empty (BR-2, AC-003)', function () {
    $actor = rewardStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses', ['name' => 'Nope1', 'color' => null])
        ->assertStatus(422)->assertJsonValidationErrors('color');

    $this->postJson('/api/reward-statuses', ['name' => 'Nope2', 'color' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

it('update: 422 when color is submitted as null or empty (BR-2, AC-003)', function () {
    $actor = rewardStatusUserWith(['update']);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['color' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

it('create: 201 when description is omitted (AC-003)', function () {
    $actor = rewardStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses', ['name' => 'Senza descrizione', 'color' => 'teal'])
        ->assertCreated()
        ->assertJsonPath('data.description', null);
});

// ---------------------------------------------------------------------------
// show — GET /api/reward-statuses/{rewardStatus}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = rewardStatusUserWith(['view']);
    $target = RewardStatus::factory()->create(['name' => 'Attivo', 'description' => 'Una descrizione', 'color' => 'blue', 'sort_order' => 30, 'is_active' => false]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/reward-statuses/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attivo')
        ->assertJsonPath('data.description', 'Una descrizione')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.sort_order', 30)
        ->assertJsonPath('data.is_active', false);
});

it('show: 404 for a non-existent reward status', function () {
    $actor = rewardStatusUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-statuses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/reward-statuses/{rewardStatus}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the reward status', function () {
    $actor = rewardStatusUserWith(['update']);
    $target = RewardStatus::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id, 'name' => 'After']);
});

it('update: PATCH {is_active: false} deactivates the status', function () {
    $actor = rewardStatusUserWith(['update']);
    $target = RewardStatus::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// ---------------------------------------------------------------------------
// AC-007 — 403 without the permission on EVERY verb, no write (precedence)
// ---------------------------------------------------------------------------

it('GET show: 403 without reward-statuses.view (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/reward-statuses/{$target->id}")->assertForbidden();
});

it('POST create: 403 without reward-statuses.create, no row created (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $countBefore = RewardStatus::count();

    $this->postJson('/api/reward-statuses', ['name' => 'Nope', 'color' => 'blue'])->assertForbidden();

    expect(RewardStatus::count())->toBe($countBefore);
});

it('PATCH update: 403 without reward-statuses.update, no change persisted (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without reward-statuses.delete, record still exists (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/reward-statuses/{rewardStatus} (BR-4, AC-004, AC-006)
// ---------------------------------------------------------------------------

it('delete: 204 + removed when not referenced by anything and not a system row (AC-004)', function () {
    $actor = rewardStatusUserWith(['delete']);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('reward_statuses', ['id' => $target->id]);
});

it('delete: 409 when referenced by a reward, status AND reward still exist (BR-4, AC-006)', function () {
    $actor = rewardStatusUserWith(['delete']);
    $target = RewardStatus::factory()->create();
    $reward = Reward::factory()->create();
    $reward->forceFill(['reward_status_id' => $target->id])->save();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This reward status is used by a reward and cannot be deleted.');

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id]);
    $this->assertDatabaseHas('rewards', ['id' => $reward->id]);
});

it('delete: 403 without reward-statuses.delete', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$target->id}")->assertForbidden();
});

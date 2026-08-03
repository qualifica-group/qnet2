<?php

use App\Models\RewardStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/reward-statuses/reorder (spec 0060, D-3)
|--------------------------------------------------------------------------
|
| Unlike opportunity-statuses/pipeline-statuses (head + tail), reward-statuses
| has ONLY a head system row ("pending") and NO tail
| (RewardStatus::SYSTEM_TAIL_KEYS = []) — this is the non-regression proof
| that the generalized StatusOrderManager/SystemStatusGuard (spec 0060) still
| handles the head-only shape correctly.
*/

if (! function_exists('rewardStatusReorderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusReorderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("reward-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-statuses.{$ability}");
        }

        return $user;
    }
}

it('reorder: a valid permutation resequences the customs between the head row and the two closing rows (spec 0073, D-6)', function () {
    $actor = rewardStatusReorderUserWith(['update']);
    $first = RewardStatus::factory()->create(['name' => 'Alpha']);
    $second = RewardStatus::factory()->create(['name' => 'Beta']);
    $third = RewardStatus::factory()->create(['name' => 'Gamma']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/reward-statuses/reorder', [
        'ordered_ids' => [$third->id, $first->id, $second->id],
    ])->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$third->id]['sort_order'])->toBe(10)
        ->and($rows[$first->id]['sort_order'])->toBe(20)
        ->and($rows[$second->id]['sort_order'])->toBe(30);

    // The head row first, then the two closing rows past the last custom
    // (RewardStatus::SYSTEM_HEAD_KEYS/SYSTEM_TAIL_KEYS).
    expect($rows->firstWhere('system_key', 'pending')['sort_order'])->toBe(0)
        ->and($rows->firstWhere('system_key', 'won')['sort_order'])->toBe(40)
        ->and($rows->firstWhere('system_key', 'lost')['sort_order'])->toBe(50);
});

it('reorder: 422 when ordered_ids includes the system status id (D-3)', function () {
    $actor = rewardStatusReorderUserWith(['update']);
    $pending = RewardStatus::where('system_key', 'pending')->firstOrFail();
    $custom = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses/reorder', ['ordered_ids' => [$pending->id, $custom->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids is missing a custom id (D-3)', function () {
    $actor = rewardStatusReorderUserWith(['update']);
    RewardStatus::factory()->create();
    $second = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses/reorder', ['ordered_ids' => [$second->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids contains a duplicate (D-3)', function () {
    $actor = rewardStatusReorderUserWith(['update']);
    $custom = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses/reorder', ['ordered_ids' => [$custom->id, $custom->id]])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids.0');
});

it('reorder: 422 when ordered_ids includes a non-existent id (D-3)', function () {
    $actor = rewardStatusReorderUserWith(['update']);
    $custom = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses/reorder', ['ordered_ids' => [$custom->id, 999999]])
        ->assertStatus(422);
});

it('reorder: 403 without reward-statuses.update, order unchanged (D-3)', function () {
    $actor = rewardStatusReorderUserWith([]);
    $custom = RewardStatus::factory()->create(['sort_order' => 20]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses/reorder', ['ordered_ids' => [$custom->id]])->assertForbidden();

    $this->assertDatabaseHas('reward_statuses', ['id' => $custom->id, 'sort_order' => 20]);
});

it('create: the first custom row lands right after the head row (spec 0073, D-6)', function () {
    $actor = rewardStatusReorderUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses', ['name' => 'Primo Custom', 'color' => 'blue', 'group' => 'pending'])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 10);

    $ordered = RewardStatus::query()->orderBy('sort_order')->pluck('name');

    expect($ordered->all())->toBe(['In attesa', 'Primo Custom', 'Approvato', 'Negato']);
});

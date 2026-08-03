<?php

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `PATCH /api/rewards/{reward}` (spec 0060 §4, D-1, BR-8) — the card's
 * inline status edit. Self-contained helpers, same isolation precedent as
 * RewardTypeDeleteGuardTest.php.
 */
uses(RefreshDatabase::class);

if (! function_exists('updateStatusActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function updateStatusActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("rewarded-referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("rewarded-referents.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('updateStatusReward')) {
    function updateStatusReward(): Reward
    {
        return Reward::factory()
            ->for(Referent::factory())
            ->for(Opportunity::factory(), 'source')
            ->create();
    }
}

it('200: an active status is applied and the full RewardResource is returned (AC-021)', function () {
    $actor = updateStatusActor(['update']);
    $reward = updateStatusReward();
    $newStatus = RewardStatus::factory()->create(['name' => 'Consegnato', 'color' => 'green', 'is_active' => true]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => $newStatus->id])
        ->assertOk()
        ->assertJsonStructure([
            'success', 'message',
            'data' => [
                'id', 'assigned_at', 'notes',
                'reward_type' => ['id', 'name', 'color'],
                'reward_status' => ['id', 'name', 'color'],
                'source' => ['type', 'id', 'name', 'path'],
                'context',
            ],
        ]);

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('data.id'))->toBe($reward->id)
        ->and($response->json('data.reward_status'))->toBe(['id' => $newStatus->id, 'name' => 'Consegnato', 'color' => 'green']);

    $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'reward_status_id' => $newStatus->id]);
});

it('200: changing the status touches ONLY reward_status_id, every other field untouched (AC-021)', function () {
    $actor = updateStatusActor(['update']);
    $reward = updateStatusReward();
    $originalNotes = $reward->notes;
    $originalAssignedAt = $reward->assigned_at->toDateString();
    $newStatus = RewardStatus::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => $newStatus->id])->assertOk();

    $fresh = $reward->fresh();
    expect($fresh->notes)->toBe($originalNotes)
        ->and($fresh->assigned_at->toDateString())->toBe($originalAssignedAt)
        ->and($fresh->reward_type_id)->toBe($reward->reward_type_id);
});

it('422: an inactive status is rejected, nothing changed (D-7/BR-8)', function () {
    $actor = updateStatusActor(['update']);
    $reward = updateStatusReward();
    $inactiveStatus = RewardStatus::factory()->create(['is_active' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => $inactiveStatus->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reward_status_id');

    expect($reward->fresh()->reward_status_id)->toBe($reward->reward_status_id);
});

it('422: a non-existent status id is rejected (BR-8)', function () {
    $actor = updateStatusActor(['update']);
    $reward = updateStatusReward();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reward_status_id');
});

it('422: reward_status_id is required', function () {
    $actor = updateStatusActor(['update']);
    $reward = updateStatusReward();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/rewards/{$reward->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reward_status_id');
});

it('403: without rewarded-referents.update, a rule-VALID payload still 403s, nothing changed (BR-8)', function () {
    $actor = updateStatusActor([]);
    $reward = updateStatusReward();
    $newStatus = RewardStatus::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => $newStatus->id])->assertForbidden();

    expect($reward->fresh()->reward_status_id)->toBe($reward->reward_status_id);
});

it('404: a non-existent reward', function () {
    $actor = updateStatusActor(['update']);
    $newStatus = RewardStatus::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/rewards/999999', ['reward_status_id' => $newStatus->id])->assertNotFound();
});

it('401: requires authentication', function () {
    $reward = updateStatusReward();

    $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => $reward->reward_status_id])
        ->assertUnauthorized();
});

it('GET /api/referents/{referent}/rewards exposes reward_status for every item and reflects a PATCH (AC-020)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        Permission::findOrCreate("rewarded-referents.{$ability}");
    }
    $actor = updateStatusActor(['view', 'update']);
    $referent = Referent::factory()->create();
    $reward = Reward::factory()->for($referent)->for(Opportunity::factory(), 'source')->create();
    $newStatus = RewardStatus::factory()->create(['name' => 'In corso', 'color' => 'blue', 'is_active' => true]);
    Sanctum::actingAs($actor);

    $before = $this->getJson("/api/referents/{$referent->id}/rewards")->assertOk()->json('data.items.0');
    expect($before['reward_status'])->toBe([
        'id' => $reward->reward_status_id,
        'name' => $reward->rewardStatus->name,
        'color' => $reward->rewardStatus->color,
    ]);

    $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => $newStatus->id])->assertOk();

    $after = $this->getJson("/api/referents/{$referent->id}/rewards")->assertOk()->json('data.items.0');
    expect($after['reward_status'])->toBe(['id' => $newStatus->id, 'name' => 'In corso', 'color' => 'blue'])
        ->and($after['reward_type'])->toBe($before['reward_type'])
        ->and($after['notes'])->toBe($before['notes']);
});

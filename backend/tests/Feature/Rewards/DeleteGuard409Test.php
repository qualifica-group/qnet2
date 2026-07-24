<?php

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * AC-018/BR-4 (spec 0060): `rewards.reward_status_id` activates the 409
 * branch of RewardStatusService::delete() — mirrors
 * RewardTypeDeleteGuardTest.php's own precedent exactly, one level down the
 * stack (reward_types -> reward_statuses).
 */
uses(RefreshDatabase::class);

if (! function_exists('rewardStatusDeleteGuardActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusDeleteGuardActor(array $abilities): User
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

it('DELETE a reward status referenced by a reward -> 409, row not deleted (AC-018)', function () {
    $actor = rewardStatusDeleteGuardActor(['delete']);
    $rewardStatus = RewardStatus::factory()->create();
    Reward::factory()
        ->for(Referent::factory())
        ->for(Opportunity::factory(), 'source')
        ->create(['reward_status_id' => $rewardStatus->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$rewardStatus->id}")->assertStatus(409);

    expect(RewardStatus::query()->whereKey($rewardStatus->id)->exists())->toBeTrue();
});

it('DELETE an unreferenced, non-system reward status -> 204, still removed (AC-018 non-regression)', function () {
    $actor = rewardStatusDeleteGuardActor(['delete']);
    $rewardStatus = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$rewardStatus->id}")->assertNoContent();

    expect(RewardStatus::query()->whereKey($rewardStatus->id)->exists())->toBeFalse();
});

it('the FK is restrictOnDelete: a raw DB delete of a referenced status is rejected too (AC-018, defense in depth)', function () {
    $rewardStatus = RewardStatus::factory()->create();
    Reward::factory()
        ->for(Referent::factory())
        ->for(Opportunity::factory(), 'source')
        ->create(['reward_status_id' => $rewardStatus->id]);

    expect(fn () => $rewardStatus->delete())->toThrow(QueryException::class);

    expect(RewardStatus::query()->whereKey($rewardStatus->id)->exists())->toBeTrue();
});

<?php

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * AC-003 (second half): `rewards.reward_type_id` is the FIRST FK ever to
 * reference `reward_types` (spec 0059) — this activates the 409 branch of
 * RewardTypeService::delete() that was a documented no-op placeholder since
 * spec 0058. The first half (Referent cascade) lives in MT-2's own
 * RewardModelTest.php.
 */
uses(RefreshDatabase::class);

if (! function_exists('rewardTypeDeleteGuardActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardTypeDeleteGuardActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("reward-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-types.{$ability}");
        }

        return $user;
    }
}

it('DELETE a reward type referenced by a reward -> 409, row not deleted (AC-003)', function () {
    $actor = rewardTypeDeleteGuardActor(['delete']);
    $rewardType = RewardType::factory()->create();
    Reward::factory()
        ->for(Referent::factory())
        ->for(Opportunity::factory(), 'source')
        ->create(['reward_type_id' => $rewardType->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-types/{$rewardType->id}")->assertStatus(409);

    expect(RewardType::query()->whereKey($rewardType->id)->exists())->toBeTrue();
});

it('DELETE an unreferenced reward type -> 204, still removed (AC-003 non-regression)', function () {
    $actor = rewardTypeDeleteGuardActor(['delete']);
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-types/{$rewardType->id}")->assertNoContent();

    expect(RewardType::query()->whereKey($rewardType->id)->exists())->toBeFalse();
});

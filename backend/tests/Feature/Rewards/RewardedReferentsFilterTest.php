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
 * AC-022 (spec 0060 §5): the `reward_status` advanced filter on
 * `rewarded-referents`, MIRRORED from RewardedReferentsAdvancedFiltersTest.
 * php's own `reward_type` test. Self-contained helpers (not reused across
 * test files/directories, same isolation precedent as
 * RewardAssignmentTest.php).
 */
uses(RefreshDatabase::class);

if (! function_exists('rewardedReferentsFilterUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardedReferentsFilterUserWith(array $abilities): User
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

if (! function_exists('rewardedReferentsFilterReward')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function rewardedReferentsFilterReward(Referent $referent, array $attributes = []): Reward
    {
        return Reward::factory()
            ->for($referent)
            ->for(Opportunity::factory(), 'source')
            ->create($attributes);
    }
}

it('reward_status advanced filter restricts to referents with a matching reward status (AC-022)', function () {
    $actor = rewardedReferentsFilterUserWith(['viewAny']);
    $wantedStatus = RewardStatus::factory()->create();
    $otherStatus = RewardStatus::factory()->create();

    $matching = Referent::factory()->create();
    rewardedReferentsFilterReward($matching, ['reward_status_id' => $wantedStatus->id]);
    $nonMatching = Referent::factory()->create();
    rewardedReferentsFilterReward($nonMatching, ['reward_status_id' => $otherStatus->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['reward_status' => [$wantedStatus->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('reward_status advanced filter accepts multiple ids (AC-022)', function () {
    $actor = rewardedReferentsFilterUserWith(['viewAny']);
    $statusA = RewardStatus::factory()->create();
    $statusB = RewardStatus::factory()->create();
    $statusC = RewardStatus::factory()->create();

    $matchingA = Referent::factory()->create();
    rewardedReferentsFilterReward($matchingA, ['reward_status_id' => $statusA->id]);
    $matchingB = Referent::factory()->create();
    rewardedReferentsFilterReward($matchingB, ['reward_status_id' => $statusB->id]);
    $nonMatching = Referent::factory()->create();
    rewardedReferentsFilterReward($nonMatching, ['reward_status_id' => $statusC->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['reward_status' => [$statusA->id, $statusB->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())
        ->toEqualCanonicalizing([$matchingA->id, $matchingB->id]);
});

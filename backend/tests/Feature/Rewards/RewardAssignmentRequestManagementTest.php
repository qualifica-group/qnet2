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
 * AC-023: `PATCH /api/request-management/{opportunity}` accepts the same
 * `rewards` field as the opportunities payload, with IDENTICAL semantics and
 * error codes — same writer (RewardAssignmentWriter), same D-3 guards
 * (ValidatesRewards), reached through RequestManagementService::updateWork()
 * instead of OpportunityService::update().
 *
 * The single most fragile invariant here (flagged explicitly): the three
 * states of the field must stay distinguishable — key ABSENT leaves rewards
 * untouched, `[]` clears every assignment, a non-empty array is an
 * authoritative replace. Collapsing "absent" and "empty" would silently wipe
 * rewards on every unrelated panel save.
 */
uses(RefreshDatabase::class);

if (! function_exists('rewardAssignmentRmActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardAssignmentRmActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('rewardAssignmentRmManagedOpportunity')) {
    /**
     * An opportunity the actor is scoped to (D-3, GA2 pivot position),
     * mirroring RequestManagementUpdateTest's own precedent.
     */
    function rewardAssignmentRmManagedOpportunity(User $manager, ?int $reporterId = null): Opportunity
    {
        $opportunity = Opportunity::factory()->create(['reporter_id' => $reporterId]);
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

it('PATCH with rewards + an existing reporter persists the reward row (AC-023)', function () {
    $actor = rewardAssignmentRmActor(['update']);
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    $opportunity = rewardAssignmentRmManagedOpportunity($actor, $reporter->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ])->assertOk();

    $this->assertDatabaseHas('rewards', [
        'referent_id' => $reporter->id,
        'reward_type_id' => $rewardType->id,
        'source_type' => 'opportunity',
        'source_id' => $opportunity->id,
    ]);
});

it('PATCH with rewards absent leaves the collection untouched (AC-023)', function () {
    $actor = rewardAssignmentRmActor(['update']);
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    $opportunity = rewardAssignmentRmManagedOpportunity($actor, $reporter->id);
    Reward::factory()->for($opportunity, 'source')->for($reporter)->for($rewardType)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['note' => null])->assertOk();

    expect(Reward::query()->where('source_id', $opportunity->id)->count())->toBe(1);
});

it('PATCH with rewards: [] clears every assignment (AC-023)', function () {
    $actor = rewardAssignmentRmActor(['update']);
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    $opportunity = rewardAssignmentRmManagedOpportunity($actor, $reporter->id);
    Reward::factory()->for($opportunity, 'source')->for($reporter)->for($rewardType)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['rewards' => []])->assertOk();

    expect(Reward::query()->where('source_id', $opportunity->id)->count())->toBe(0);
});

it('PATCH with rewards non-empty and no reporter -> 422 on rewards, identical error code to the opportunities channel (AC-023)', function () {
    $actor = rewardAssignmentRmActor(['update']);
    $rewardType = RewardType::factory()->create();
    $opportunity = rewardAssignmentRmManagedOpportunity($actor, reporterId: null);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('rewards');

    expect(Reward::query()->where('source_id', $opportunity->id)->count())->toBe(0);
});

it('PATCH clearing reporter_id while rewards exist -> 422 on reporter_id (AC-023)', function () {
    $actor = rewardAssignmentRmActor(['update']);
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    $opportunity = rewardAssignmentRmManagedOpportunity($actor, $reporter->id);
    Reward::factory()->for($opportunity, 'source')->for($reporter)->for($rewardType)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['reporter_id' => null])
        ->assertStatus(422)->assertJsonValidationErrors('reporter_id');

    expect($opportunity->fresh()->reporter_id)->toBe($reporter->id);
});

it('PATCH changing reporter_id retargets every existing reward (AC-023, same rule as AC-022)', function () {
    $actor = rewardAssignmentRmActor(['update']);
    $originalReporter = Referent::factory()->create();
    $newReporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    $opportunity = rewardAssignmentRmManagedOpportunity($actor, $originalReporter->id);
    Reward::factory()->for($opportunity, 'source')->for($originalReporter)->for($rewardType)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['reporter_id' => $newReporter->id])->assertOk();

    $referentIds = Reward::query()->where('source_id', $opportunity->id)->pluck('referent_id')->unique()->all();
    expect($referentIds)->toBe([$newReporter->id]);
});

<?php

use App\Enums\StatusSystemKey;
use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\RewardType;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0073: the request -> reward lifecycle automation. A request entering a
 * `closed_lost` WORKING status closes its buoni negatively, saving what each
 * one carried; reopening restores them verbatim.
 *
 * The three system reward statuses this suite leans on ("In attesa"/"Chiuso
 * negativo"/"Aperto") are seeded by the migrations, so they are READ back
 * here, never created (system_key is UNIQUE).
 */
uses(RefreshDatabase::class);

if (! function_exists('rewardLifecycleActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardLifecycleActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('rewardLifecycleOpportunity')) {
    function rewardLifecycleOpportunity(User $manager, Referent $reporter): Opportunity
    {
        $opportunity = Opportunity::factory()->create(['reporter_id' => $reporter->id]);
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

if (! function_exists('rewardLifecycleStatus')) {
    /** A system reward status, read back by `system_key`. */
    function rewardLifecycleStatus(StatusSystemKey $systemKey): RewardStatus
    {
        return RewardStatus::query()->where('system_key', $systemKey->value)->firstOrFail();
    }
}

if (! function_exists('rewardLifecycleWorkflowStatus')) {
    /** A row of the GLOBAL default working-status set (spec 0047, AC-005). */
    function rewardLifecycleWorkflowStatus(string $systemKey): OpportunityWorkflowStatus
    {
        return OpportunityWorkflowStatus::query()
            ->whereNull('opportunity_workflow_id')
            ->where('system_key', $systemKey)
            ->firstOrFail();
    }
}

if (! function_exists('rewardLifecycleReward')) {
    function rewardLifecycleReward(Opportunity $opportunity, Referent $reporter, RewardStatus $status): Reward
    {
        return Reward::factory()
            ->for($opportunity, 'source')
            ->for($reporter)
            ->for(RewardType::factory())
            ->create(['reward_status_id' => $status->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-007 — closing the request closes its buoni, saving the previous status
// ---------------------------------------------------------------------------

it('PATCH to a closed_lost working status closes every reward and saves what it carried (AC-007)', function () {
    $actor = rewardLifecycleActor(['update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);

    $pending = rewardLifecycleStatus(StatusSystemKey::Pending);
    $custom = RewardStatus::factory()->create(['name' => 'Approvato']);
    $closedNegative = rewardLifecycleStatus(StatusSystemKey::Lost);

    $first = rewardLifecycleReward($opportunity, $reporter, $pending);
    $second = rewardLifecycleReward($opportunity, $reporter, $custom);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_lost')->id,
    ])->assertOk();

    expect($first->fresh()->reward_status_id)->toBe($closedNegative->id)
        ->and($first->fresh()->status_before_closure_id)->toBe($pending->id)
        ->and($second->fresh()->reward_status_id)->toBe($closedNegative->id)
        ->and($second->fresh()->status_before_closure_id)->toBe($custom->id);
});

// ---------------------------------------------------------------------------
// AC-008 — reopening restores each reward verbatim
// ---------------------------------------------------------------------------

it('PATCH back to a non-closed_lost working status restores each reward and clears the marker (AC-008)', function () {
    $actor = rewardLifecycleActor(['update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);

    $pending = rewardLifecycleStatus(StatusSystemKey::Pending);
    $custom = RewardStatus::factory()->create(['name' => 'Approvato']);

    $first = rewardLifecycleReward($opportunity, $reporter, $pending);
    $second = rewardLifecycleReward($opportunity, $reporter, $custom);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_lost')->id,
    ])->assertOk();

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('open')->id,
    ])->assertOk();

    expect($first->fresh()->reward_status_id)->toBe($pending->id)
        ->and($first->fresh()->status_before_closure_id)->toBeNull()
        ->and($second->fresh()->reward_status_id)->toBe($custom->id)
        ->and($second->fresh()->status_before_closure_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-009 — a POSITIVE closure leaves the buoni alone (D-3)
// ---------------------------------------------------------------------------

it('PATCH to a closed_won working status leaves the rewards untouched (AC-009)', function () {
    $actor = rewardLifecycleActor(['update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);
    $pending = rewardLifecycleStatus(StatusSystemKey::Pending);
    $reward = rewardLifecycleReward($opportunity, $reporter, $pending);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_won')->id,
    ])->assertOk();

    expect($reward->fresh()->reward_status_id)->toBe($pending->id)
        ->and($reward->fresh()->status_before_closure_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-010 — a second closing pass never overwrites the saved status
// ---------------------------------------------------------------------------

it('a second closed_lost transition keeps the ORIGINAL saved status (idempotence, AC-010)', function () {
    $actor = rewardLifecycleActor(['update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);
    $pending = rewardLifecycleStatus(StatusSystemKey::Pending);
    $reward = rewardLifecycleReward($opportunity, $reporter, $pending);

    $otherClosedNegative = OpportunityWorkflowStatus::factory()->global()->create([
        'name' => 'Persa per prezzo',
        'group' => WorkflowStatusGroup::ClosedLost,
        'sort_order' => 99,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_lost')->id,
    ])->assertOk();

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => $otherClosedNegative->id,
    ])->assertOk();

    expect($reward->fresh()->status_before_closure_id)->toBe($pending->id);
});

// ---------------------------------------------------------------------------
// AC-011 — a reward ADDED by the very PATCH that closes the request
// ---------------------------------------------------------------------------

it('a reward assigned in the same PATCH that closes the request is closed too (AC-011)', function () {
    $actor = rewardLifecycleActor(['update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_lost')->id,
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ])->assertOk();

    $reward = Reward::query()->where('source_id', $opportunity->id)->firstOrFail();

    expect($reward->reward_status_id)->toBe(rewardLifecycleStatus(StatusSystemKey::Lost)->id)
        ->and($reward->status_before_closure_id)->toBe(rewardLifecycleStatus(StatusSystemKey::Pending)->id);
});

// ---------------------------------------------------------------------------
// AC-012 — the other write channels of the working status
// ---------------------------------------------------------------------------

it('the inline-edit channel closes the rewards too (AC-012)', function () {
    $actor = rewardLifecycleActor(['viewAny', 'view', 'update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);
    $reward = rewardLifecycleReward($opportunity, $reporter, rewardLifecycleStatus(StatusSystemKey::Pending));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => rewardLifecycleWorkflowStatus('closed_lost')->id,
    ])->assertOk();

    expect($reward->fresh()->reward_status_id)->toBe(rewardLifecycleStatus(StatusSystemKey::Lost)->id);
});

it('the opportunities update channel closes the rewards too (AC-012)', function () {
    Permission::findOrCreate('opportunities.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.update');

    $reporter = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['reporter_id' => $reporter->id]);
    $reward = rewardLifecycleReward($opportunity, $reporter, rewardLifecycleStatus(StatusSystemKey::Pending));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_lost')->id,
    ])->assertOk();

    expect($reward->fresh()->reward_status_id)->toBe(rewardLifecycleStatus(StatusSystemKey::Lost)->id)
        ->and($reward->fresh()->status_before_closure_id)->toBe(rewardLifecycleStatus(StatusSystemKey::Pending)->id);
});

it('the workflow delete-reassign lane reopens the rewards it moves out of closed_lost (AC-012)', function () {
    foreach (['delete'] as $ability) {
        Permission::findOrCreate("opportunity-workflows.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunity-workflows.delete');

    $source = Source::factory()->create();
    $workflow = OpportunityWorkflow::factory()->create(['is_active' => true]);
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $closedLost = OpportunityWorkflowStatus::factory()
        ->system('closed_lost')
        ->create(['opportunity_workflow_id' => $workflow->id]);

    $reporter = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'source_id' => $source->id,
        'reporter_id' => $reporter->id,
        'opportunity_workflow_status_id' => $closedLost->id,
    ]);

    $pending = rewardLifecycleStatus(StatusSystemKey::Pending);
    $reward = rewardLifecycleReward($opportunity, $reporter, rewardLifecycleStatus(StatusSystemKey::Lost));
    $reward->status_before_closure_id = $pending->id;
    $reward->save();

    Sanctum::actingAs($actor);

    // The FK is nullOnDelete, so the re-resolution lands the opportunity on
    // the GLOBAL 'open' row — a reopening, as far as the rewards go.
    $this->deleteJson("/api/opportunity-workflows/{$workflow->id}")->assertNoContent();

    expect($reward->fresh()->reward_status_id)->toBe($pending->id)
        ->and($reward->fresh()->status_before_closure_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-013 — blast radius: only this request's rewards, none created/deleted
// ---------------------------------------------------------------------------

it('rewards of another request are never touched, and none is created or deleted (AC-013)', function () {
    $actor = rewardLifecycleActor(['update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);
    $otherOpportunity = rewardLifecycleOpportunity($actor, $reporter);

    $pending = rewardLifecycleStatus(StatusSystemKey::Pending);
    rewardLifecycleReward($opportunity, $reporter, $pending);
    $foreign = rewardLifecycleReward($otherOpportunity, $reporter, $pending);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_lost')->id,
    ])->assertOk();

    expect($foreign->fresh()->reward_status_id)->toBe($pending->id)
        ->and($foreign->fresh()->status_before_closure_id)->toBeNull()
        ->and(Reward::query()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-014 — the movements are traceable as AUTOMATIC
// ---------------------------------------------------------------------------

it('logs reward.auto_closed on closure and reward.auto_reopened on reopening (AC-014)', function () {
    $actor = rewardLifecycleActor(['update']);
    $reporter = Referent::factory()->create();
    $opportunity = rewardLifecycleOpportunity($actor, $reporter);
    $reward = rewardLifecycleReward($opportunity, $reporter, rewardLifecycleStatus(StatusSystemKey::Pending));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('closed_lost')->id,
    ])->assertOk();

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => rewardLifecycleWorkflowStatus('open')->id,
    ])->assertOk();

    $events = DB::table('activity_log')
        ->where('subject_type', 'reward')
        ->where('subject_id', $reward->id)
        ->pluck('event')
        ->all();

    expect($events)->toContain('reward.auto_closed')
        ->and($events)->toContain('reward.auto_reopened');
});

// ---------------------------------------------------------------------------
// AC-015 — the manual card edit stays untouched by the automation
// ---------------------------------------------------------------------------

it('the manual PATCH /api/rewards/{reward} never writes the saved status (AC-015)', function () {
    Permission::findOrCreate('rewarded-referents.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('rewarded-referents.update');

    $reporter = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['reporter_id' => $reporter->id]);
    $reward = rewardLifecycleReward($opportunity, $reporter, rewardLifecycleStatus(StatusSystemKey::Pending));
    $target = RewardStatus::factory()->create(['name' => 'Consegnato']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/rewards/{$reward->id}", ['reward_status_id' => $target->id])->assertOk();

    expect($reward->fresh()->reward_status_id)->toBe($target->id)
        ->and($reward->fresh()->status_before_closure_id)->toBeNull();
});

<?php

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\RewardType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * AC-002: `source()` resolves the polymorphic origin and `source_type`
 * stores the morph map ALIAS ('opportunity'), never the FQCN — the strict
 * `Relation::enforceMorphMap()` (AppServiceProvider) is what makes this
 * assertion meaningful.
 */
it('associates a Reward to an Opportunity storing the morph alias, not the FQCN (AC-002)', function () {
    $opportunity = Opportunity::factory()->create();

    $reward = new Reward([
        'referent_id' => Referent::factory()->create()->id,
        'reward_type_id' => RewardType::factory()->create()->id,
        'reward_status_id' => RewardStatus::factory()->create()->id,
        'assigned_at' => now()->toDateString(),
    ]);
    $reward->source()->associate($opportunity);
    $reward->save();

    expect($reward->fresh()->getAttributes()['source_type'])->toBe('opportunity')
        ->and($reward->getAttributes()['source_type'])->not->toBe(Opportunity::class)
        ->and($reward->fresh()->source)->toBeInstanceOf(Opportunity::class)
        ->and($reward->fresh()->source->id)->toBe($opportunity->id);
});

/**
 * AC-003 (first half): deleting a Referent cascades onto its Reward rows —
 * the DB-level `cascadeOnDelete()` FK on `referent_id`. The reward-types 409
 * branch is the SECOND half of AC-003, owned by MT-5.
 */
it('cascades: deleting a referent deletes its rewards (AC-003)', function () {
    $referent = Referent::factory()->create();
    $survivor = Referent::factory()->create();

    $reward = Reward::factory()->for($referent)->create();
    $untouched = Reward::factory()->for($survivor)->create();

    $referent->delete();

    expect(Reward::query()->whereKey($reward->id)->exists())->toBeFalse()
        ->and(Reward::query()->whereKey($untouched->id)->exists())->toBeTrue();
});

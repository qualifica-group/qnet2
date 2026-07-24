<?php

use App\ActivityLog\ActivityLogRegistry;
use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * AC-024, plus the documented config/activity-log.php namespace trap
 * (HANDOFF 0058): `'rewards' => ['model' => Reward::class]` resolves to the
 * FQCN only if `use App\Models\Reward;` is present at the top of the config
 * file — a missing import silently resolves to the bare string 'Reward' and
 * `php -l` stays green. The FIRST assertion below fails loudly if that
 * import is ever dropped.
 */
it('config/activity-log.php resolves the rewards resource to the Reward FQCN', function () {
    $definition = app(ActivityLogRegistry::class)->resolve('rewards');

    expect($definition->model)->toBe(Reward::class);
});

/**
 * AC-024: create/update/delete of a Reward each produce a recoverable
 * activity-log entry, and `subject_type` stores the morph map ALIAS
 * ('reward'), not the FQCN nor the bare class name — the same trap
 * RewardTypeActivityLogTest guards for `reward_type`.
 */
it('create/update/delete produce activity-log entries with subject_type = reward (AC-024)', function () {
    $opportunity = Opportunity::factory()->create();
    $referent = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();

    $reward = new Reward([
        'referent_id' => $referent->id,
        'reward_type_id' => $rewardType->id,
        'assigned_at' => now()->toDateString(),
        'notes' => 'Initial note',
    ]);
    $reward->source()->associate($opportunity);
    $reward->save();

    $reward->update(['notes' => 'Updated note']);

    $id = $reward->id;
    $reward->delete();

    $events = Activity::query()
        ->where('subject_type', $reward->getMorphClass())
        ->where('subject_id', $id)
        ->orderBy('id')
        ->pluck('description');

    expect($reward->getMorphClass())->toBe('reward')
        ->and($events)->toContain('created', 'updated', 'deleted');
});

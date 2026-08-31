<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Segnalatore diritto al buono" on the OFFERTA (user directive 2026-08-31):
 * the same abbinamento the Opportunita' form already carried, now on the
 * `quotes` module too. The write path is the shared RewardAssignmentWriter
 * (spec 0059 D-3, generalized to Quote by spec 0086 D-12) — this file pins
 * the `quotes` endpoints' half of it: validation, sync, retarget and the
 * `rewards` block of QuoteResource.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteRewardActor')) {
    function quoteRewardActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update']);

        return $user;
    }
}

it('POST /api/quotes assigns the submitted rewards to the offer, beneficiary the offer reporter', function () {
    $reporter = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $type = RewardType::factory()->create();
    Sanctum::actingAs(quoteRewardActor());

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $type->id]],
    ])->assertCreated()->json('data.id');

    $reward = Reward::query()->sole();

    expect($reward->source_type)->toBe('quote')
        ->and($reward->source_id)->toBe($quoteId)
        ->and($reward->referent_id)->toBe($reporter->id)
        ->and($reward->reward_type_id)->toBe($type->id);
});

it('POST /api/quotes does NOT inherit the opportunity rewards (user directive 2026-08-31)', function () {
    $reporter = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['reporter_id' => $reporter->id]);
    Reward::factory()->for($reporter)->create([
        'source_type' => 'opportunity',
        'source_id' => $opportunity->id,
    ]);
    Sanctum::actingAs(quoteRewardActor());

    $this->postJson('/api/quotes', ['title' => 'Offerta', 'opportunity_id' => $opportunity->id])
        ->assertCreated()
        ->assertJsonPath('data.rewards', []);

    expect(Reward::query()->where('source_type', 'quote')->count())->toBe(0);
});

it('POST /api/quotes rejects rewards without a reporter (422)', function () {
    $opportunity = Opportunity::factory()->create(['reporter_id' => null]);
    $type = RewardType::factory()->create();
    Sanctum::actingAs(quoteRewardActor());

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'reporter_id' => null,
        'rewards' => [['reward_type_id' => $type->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('rewards');
});

it('PATCH /api/quotes/{quote} full-replaces the reward set and [] clears it', function () {
    $reporter = Referent::factory()->create();
    $quote = Quote::factory()->create(['reporter_id' => $reporter->id]);
    $kept = RewardType::factory()->create();
    $added = RewardType::factory()->create();
    Sanctum::actingAs(quoteRewardActor());

    $this->patchJson("/api/quotes/{$quote->id}", [
        'rewards' => [['reward_type_id' => $kept->id], ['reward_type_id' => $added->id]],
    ])->assertOk();

    expect($quote->rewards()->pluck('reward_type_id')->sort()->values()->all())
        ->toBe(collect([$kept->id, $added->id])->sort()->values()->all());

    $this->patchJson("/api/quotes/{$quote->id}", ['rewards' => []])->assertOk();

    expect($quote->rewards()->count())->toBe(0);
});

it('PATCH /api/quotes/{quote} leaves the persisted set untouched when `rewards` is absent', function () {
    $reporter = Referent::factory()->create();
    $quote = Quote::factory()->create(['reporter_id' => $reporter->id]);
    Reward::factory()->for($reporter)->create(['source_type' => 'quote', 'source_id' => $quote->id]);
    Sanctum::actingAs(quoteRewardActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Rinominata'])->assertOk();

    expect($quote->rewards()->count())->toBe(1);
});

it('PATCH /api/quotes/{quote} retargets every reward when the reporter changes', function () {
    $oldReporter = Referent::factory()->create();
    $newReporter = Referent::factory()->create();
    $quote = Quote::factory()->create(['reporter_id' => $oldReporter->id]);
    $reward = Reward::factory()->for($oldReporter)->create([
        'source_type' => 'quote',
        'source_id' => $quote->id,
    ]);
    Sanctum::actingAs(quoteRewardActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['reporter_id' => $newReporter->id])->assertOk();

    expect($reward->fresh()->referent_id)->toBe($newReporter->id);
});

it('PATCH /api/quotes/{quote} refuses to clear the reporter while rewards are attached (422)', function () {
    $reporter = Referent::factory()->create();
    $quote = Quote::factory()->create(['reporter_id' => $reporter->id]);
    Reward::factory()->for($reporter)->create(['source_type' => 'quote', 'source_id' => $quote->id]);
    Sanctum::actingAs(quoteRewardActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['reporter_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reporter_id');
});

it('GET /api/quotes/{quote} embeds the rewards block ordered by reward type name', function () {
    $reporter = Referent::factory()->create();
    $quote = Quote::factory()->create(['reporter_id' => $reporter->id]);
    $zeta = RewardType::factory()->create(['name' => 'Zeta', 'color' => 'red']);
    $alfa = RewardType::factory()->create(['name' => 'Alfa', 'color' => 'blue']);
    Reward::factory()->for($reporter)->for($zeta)->create(['source_type' => 'quote', 'source_id' => $quote->id]);
    Reward::factory()->for($reporter)->for($alfa)->create(['source_type' => 'quote', 'source_id' => $quote->id]);
    Sanctum::actingAs(quoteRewardActor());

    $rewards = $this->getJson("/api/quotes/{$quote->id}")->assertOk()->json('data.rewards');

    expect(array_column(array_column($rewards, 'reward_type'), 'name'))->toBe(['Alfa', 'Zeta'])
        ->and($rewards[0]['reward_type'])->toBe(['id' => $alfa->id, 'name' => 'Alfa', 'color' => 'blue']);
});

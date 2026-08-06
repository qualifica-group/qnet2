<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Reward;
use App\Models\RewardType;
use App\Models\User;
use App\Services\Opportunities\OpportunityStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/referents/{referent}/rewards (spec 0059, data_contract §3) — the
// lazy detail endpoint feeding the `rewarded-referents` AG Grid master/
// detail: AC-014..AC-018.

uses(RefreshDatabase::class);

/**
 * The RewardedReferentsPolicy that would normally register this permission
 * (spec 0059, precedent request-management) may not exist yet at the time
 * this suite runs (parallel MT-3) — created directly here via
 * Permission::findOrCreate() rather than waiting on it, per team-lead
 * instruction.
 */
if (! function_exists('rewardsViewerActor')) {
    function rewardsViewerActor(): User
    {
        Permission::findOrCreate('rewarded-referents.view');

        $user = User::factory()->create();
        $user->givePermissionTo('rewarded-referents.view');

        return $user;
    }
}

it('returns the envelope with the exact item shape, ordered by assigned_at desc (AC-014)', function () {
    $referent = Referent::factory()->create();
    $rewardType = RewardType::factory()->create(['name' => 'Buono Amazon 50€', 'color' => 'emerald']);
    $registry = Registry::factory()->create(['name' => 'Acme Srl']);
    $quoteStatus = QuoteWorkflowStatus::factory()->create(['name' => 'In corso', 'color' => 'blue', 'group' => WorkflowStatusGroup::Open]);
    $category = ProductCategory::factory()->create(['name' => 'Software']);
    $manager = User::factory()->create(['name' => 'Mario Rossi']);

    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    // Spec 0082/0083: the context status is computed from the opportunity's quotes.
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $quoteStatus->id]);
    OpportunityProductLine::query()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $category->id,
    ]);
    $opportunity->managers()->attach($manager->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);

    // A distinct reward_type for $older: the (referent_id, reward_type_id,
    // source_type, source_id) unique constraint (rewards_unique_assignment)
    // forbids two identical assignments on the same origin.
    $older = Reward::factory()->for($referent)->for(RewardType::factory())->create([
        'source_type' => 'opportunity',
        'source_id' => $opportunity->id,
        'assigned_at' => now()->subDays(5)->toDateString(),
        'notes' => 'Prima assegnazione',
    ]);
    $newer = Reward::factory()->for($referent)->for($rewardType)->create([
        'source_type' => 'opportunity',
        'source_id' => $opportunity->id,
        'assigned_at' => now()->toDateString(),
        'notes' => null,
    ]);

    Sanctum::actingAs(rewardsViewerActor());

    $response = $this->getJson("/api/referents/{$referent->id}/rewards")
        ->assertOk()
        ->assertJsonStructure([
            'success', 'message',
            'data' => ['items' => [[
                'id', 'assigned_at', 'notes',
                'reward_type' => ['id', 'name', 'color'],
                'source' => ['type', 'id', 'name', 'path'],
                'context' => [
                    'registry' => ['id', 'name'],
                    'product_categories' => [['id', 'name']],
                    'status' => ['source', 'distinct_count', 'entries'],
                    'operator' => ['id', 'name', 'avatar_url'],
                ],
            ]]],
        ]);

    expect($response->json('success'))->toBeTrue();
    $items = $response->json('data.items');
    expect($items)->toHaveCount(2)
        ->and($items[0]['id'])->toBe($newer->id)
        ->and($items[1]['id'])->toBe($older->id);

    $item = $items[0];
    expect($item['reward_type'])->toBe(['id' => $rewardType->id, 'name' => 'Buono Amazon 50€', 'color' => 'emerald'])
        ->and($item['source'])->toBe([
            'type' => 'opportunity',
            'id' => $opportunity->id,
            'name' => $opportunity->name,
            'path' => "/opportunities/{$opportunity->id}",
        ])
        ->and($item['context']['registry'])->toBe(['id' => $registry->id, 'name' => 'Acme Srl'])
        ->and($item['context']['product_categories'])->toBe([['id' => $category->id, 'name' => 'Software']])
        ->and($item['context']['status'])->toBe([
            'source' => 'quotes',
            'distinct_count' => 1,
            'entries' => [['id' => $quoteStatus->id, 'name' => 'In corso', 'color' => 'blue', 'group' => 'open', 'count' => 1]],
        ])
        ->and($item['context'])->not->toHaveKey('workflow_status')
        ->and($item['context']['operator'])->toMatchArray(['id' => $manager->id, 'name' => 'Mario Rossi']);
});

it('exposes no workflow_status key (spec 0083, D-2) and null operator when there is no position-2 manager (AC-015)', function () {
    $referent = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Reward::factory()->for($referent)->create(['source_type' => 'opportunity', 'source_id' => $opportunity->id]);

    Sanctum::actingAs(rewardsViewerActor());

    $item = $this->getJson("/api/referents/{$referent->id}/rewards")->assertOk()->json('data.items.0');

    expect($item['context'])->not->toHaveKey('workflow_status')
        ->and($item['context']['operator'])->toBeNull();
});

it('reflects the opportunity\'s CURRENT computed status without writing to rewards (AC-016)', function () {
    $referent = Referent::factory()->create();
    $openStatus = QuoteWorkflowStatus::factory()->create(['group' => WorkflowStatusGroup::Open]);
    $closedStatus = QuoteWorkflowStatus::factory()->create(['group' => WorkflowStatusGroup::ClosedWon]);
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $openStatus->id]);
    $reward = Reward::factory()->for($referent)->create(['source_type' => 'opportunity', 'source_id' => $opportunity->id]);
    $rewardUpdatedAt = $reward->fresh()->updated_at;

    Sanctum::actingAs(rewardsViewerActor());

    $before = $this->getJson("/api/referents/{$referent->id}/rewards")->assertOk()->json('data.items.0.context.status.entries.0.group');
    expect($before)->toBe('open');

    // `quote_workflow_status_id` is deliberately absent from Quote's
    // #[Fillable] (spec 0083) — a plain update() would silently drop it.
    $quote->forceFill(['quote_workflow_status_id' => $closedStatus->id])->save();

    $after = $this->getJson("/api/referents/{$referent->id}/rewards")->assertOk()->json('data.items.0.context.status.entries.0.group');
    expect($after)->toBe('closed_won')
        ->and($reward->fresh()->updated_at->equalTo($rewardUpdatedAt))->toBeTrue();
});

it('costs the SAME number of queries for 2 rewards and for 10 rewards (AC-017)', function () {
    $small = Referent::factory()->create();
    Reward::factory()->for($small)->count(2)->create();

    $large = Referent::factory()->create();
    Reward::factory()->for($large)->count(10)->create();

    $actor = rewardsViewerActor();
    Sanctum::actingAs($actor);

    // Warm Spatie's permission cache BEFORE measuring: it lazily loads and
    // caches the full permission set on first use, which would otherwise
    // count as one extra, size-UNRELATED query on whichever call runs
    // first — a test artifact, not an N+1 in the endpoint itself.
    $actor->can('rewarded-referents.view');

    // Warm OpportunityStatusResolver's own `defaultEntry()` query BEFORE
    // measuring too: it is bound `scoped` in the container (one instance per
    // request/test), so its FIRST call anywhere pays one extra, size-UNRELATED
    // query for the GLOBAL default `open` row — the exact same kind of
    // artifact as the permission-cache warm-up above, not an N+1 in the
    // endpoint itself (a quote-less Opportunity resolves it once, then every
    // other quote-less row in the SAME request reuses the memoized result).
    app(OpportunityStatusResolver::class)->resolve(Opportunity::factory()->create());

    DB::enableQueryLog();
    $this->getJson("/api/referents/{$small->id}/rewards")->assertOk()->assertJsonCount(2, 'data.items');
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    $this->getJson("/api/referents/{$large->id}/rewards")->assertOk()->assertJsonCount(10, 'data.items');
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

it('rejects an actor without rewarded-referents.view with 403 (AC-018)', function () {
    $referent = Referent::factory()->create();
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/referents/{$referent->id}/rewards")->assertForbidden();
});

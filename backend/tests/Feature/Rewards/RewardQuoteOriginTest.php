<?php

use App\Enums\QuoteLineType;
use App\Enums\RewardStatusGroup;
use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The Offerta as a buono ORIGIN on the "Segnalatori premiati" page (user
 * directive 2026-08-31: "nella pagina dedicata ai buoni voglio che ci sia il
 * riferimento all'offerta e non solo in opportunita'"). Before this, a
 * quote-origin reward came out of RewardResource with a null name, a null
 * path and a null context — an unusable, link-less card.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteOriginRewardsViewer')) {
    function quoteOriginRewardsViewer(): User
    {
        Permission::findOrCreate('rewarded-referents.view');
        Permission::findOrCreate('rewarded-referents.viewAny');

        $user = User::factory()->create();
        $user->givePermissionTo(['rewarded-referents.view', 'rewarded-referents.viewAny']);

        return $user;
    }
}

it('projects an Offerta origin with its code, its /quotes path and its own context', function () {
    $referent = Referent::factory()->create();
    $registry = Registry::factory()->create(['name' => 'Acme Srl']);
    $category = ProductCategory::factory()->create(['name' => 'Software']);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $status = QuoteWorkflowStatus::factory()->create([
        'name' => 'In corso', 'color' => 'blue', 'group' => WorkflowStatusGroup::Open,
    ]);
    $operator = User::factory()->create(['name' => 'Mario Rossi']);

    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $quote = Quote::factory()->create([
        'opportunity_id' => $opportunity->id,
        'code' => 'QUO-0042',
        'quote_workflow_status_id' => $status->id,
        'operator_id' => $operator->id,
    ]);
    QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'line_type' => QuoteLineType::Revenue,
    ]);
    Reward::factory()->for($referent)->create(['source_type' => 'quote', 'source_id' => $quote->id]);

    Sanctum::actingAs(quoteOriginRewardsViewer());

    $item = $this->getJson("/api/referents/{$referent->id}/rewards")->assertOk()->json('data.items.0');

    expect($item['source'])->toBe([
        'type' => 'quote',
        'id' => $quote->id,
        'name' => 'QUO-0042',
        'path' => "/quotes/{$quote->id}",
    ])
        ->and($item['context']['registry'])->toBe(['id' => $registry->id, 'name' => 'Acme Srl'])
        ->and($item['context']['product_categories'])->toBe([['id' => $category->id, 'name' => 'Software']])
        ->and($item['context']['workflow_status'])->toBe(['id' => $status->id, 'name' => 'In corso', 'color' => 'blue'])
        ->and($item['context']['status']['entries'])->toHaveCount(1)
        ->and($item['context']['operator'])->toMatchArray(['id' => $operator->id, 'name' => 'Mario Rossi']);
});

it('carries the counterpart record in `related`, whichever of the two the buono was born on', function () {
    $referent = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'code' => 'QUO-0007']);

    $fromQuote = Reward::factory()->for($referent)->create(['source_type' => 'quote', 'source_id' => $quote->id]);
    $fromOpportunity = Reward::factory()->for($referent)->create([
        'source_type' => 'opportunity', 'source_id' => $opportunity->id,
    ]);

    Sanctum::actingAs(quoteOriginRewardsViewer());

    $items = collect($this->getJson("/api/referents/{$referent->id}/rewards")->assertOk()->json('data.items'))
        ->keyBy('id');

    expect($items[$fromQuote->id]['related'])->toBe([[
        'type' => 'opportunity',
        'id' => $opportunity->id,
        'name' => $opportunity->name,
        'path' => "/opportunities/{$opportunity->id}",
    ]])
        ->and($items[$fromOpportunity->id]['related'])->toBe([[
            'type' => 'quote',
            'id' => $quote->id,
            'name' => 'QUO-0007',
            'path' => "/quotes/{$quote->id}",
        ]]);
});

it('counts an Offerta-origin buono like any other, by its OWN status group', function () {
    $referent = Referent::factory()->create();
    $pending = RewardStatus::factory()->create(['group' => RewardStatusGroup::Pending]);
    $approved = RewardStatus::factory()->create(['group' => RewardStatusGroup::ClosedWon]);

    Reward::factory()->for($referent)->for(RewardType::factory())->create([
        'source_type' => 'quote',
        'source_id' => Quote::factory()->create()->id,
        'reward_status_id' => $pending->id,
    ]);
    Reward::factory()->for($referent)->for(RewardType::factory())->create([
        'source_type' => 'quote',
        'source_id' => Quote::factory()->create()->id,
        'reward_status_id' => $approved->id,
    ]);

    Sanctum::actingAs(quoteOriginRewardsViewer());

    $row = $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items.0');

    expect($row['rewards_count'])->toBe(2)
        ->and($row['pending_rewards_count'])->toBe(1)
        ->and($row['approved_rewards_count'])->toBe(1);
});

it('matches BOTH origins on the `quote` advanced filter of the offer they belong to', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $bornOnQuote = Referent::factory()->create();
    Reward::factory()->for($bornOnQuote)->create(['source_type' => 'quote', 'source_id' => $quote->id]);

    $bornOnOpportunity = Referent::factory()->create();
    Reward::factory()->for($bornOnOpportunity)->create([
        'source_type' => 'opportunity',
        'source_id' => $opportunity->id,
    ]);

    $unrelated = Referent::factory()->create();
    Reward::factory()->for($unrelated)->create([
        'source_type' => 'quote',
        'source_id' => Quote::factory()->create()->id,
    ]);

    Sanctum::actingAs(quoteOriginRewardsViewer());

    $rows = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['quote' => $quote->id],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$bornOnQuote->id, $bornOnOpportunity->id])->sort()->values()->all());
});

it('matches an Offerta-origin buono on the `opportunity` advanced filter of its parent', function () {
    $referent = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Reward::factory()->for($referent)->create(['source_type' => 'quote', 'source_id' => $quote->id]);

    $other = Referent::factory()->create();
    Reward::factory()->for($other)->create([
        'source_type' => 'quote',
        'source_id' => Quote::factory()->create()->id,
    ]);

    Sanctum::actingAs(quoteOriginRewardsViewer());

    $rows = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['opportunity' => $opportunity->id],
    ])->assertOk()->json('items');

    expect($rows)->toHaveCount(1)->and($rows[0]['id'])->toBe($referent->id);
});

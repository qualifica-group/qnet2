<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0389 (AC-003..AC-006): a branch reserved to one area gets no tab and
 * accepts no NEW link in the other area; links already saved stay valid.
 */
uses(RefreshDatabase::class);

/** A quote whose opportunity is classified with $category, visible to $actor. */
function usageAreaQuote(ProductCategory $category, User $actor): Quote
{
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    OpportunityProductLine::factory()->for($opportunity)->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    return Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
}

/** @return array<int, array{product_category_id: int}> */
function usageAreaLines(ProductCategory $category): array
{
    return [['product_category_id' => $category->id]];
}

function requestActor(): User
{
    return usageAreaActor(['request-management.viewAny', 'request-management.view', 'request-management.update', 'request-management.viewAll']);
}

// ---------------------------------------------------------------------------
// AC-003 — tabs of Gestione Richieste / Iscritti
// ---------------------------------------------------------------------------

it('AC-003: the request tabs skip the categories reserved to opportunities, at both levels', function () {
    $actor = requestActor();
    foreach (['all' => 'Alpha', 'requests' => 'Beta', 'opportunities' => 'Gamma'] as $area => $name) {
        usageAreaQuote(usageAreaCategory($area, $name), $actor);
    }
    Sanctum::actingAs($actor);

    $names = fn (string $level) => collect($this->getJson('/api/request-management/product-categories'.($level === 'root' ? '?level=root' : ''))
        ->assertOk()->json('data.categories'))->pluck('name')->all();

    expect($names('all'))->toBe(['Alpha', 'Beta'])
        ->and($names('root'))->toBe(['Alpha', 'Beta']);
});

// ---------------------------------------------------------------------------
// AC-004/AC-005 — Gestione Richieste work panel
// ---------------------------------------------------------------------------

it('AC-004/AC-005: a request refuses a NEW category of opportunities and keeps the saved one', function () {
    $actor = requestActor();
    $saved = usageAreaCategory('all', 'Saved');
    $kept = usageAreaCategory('requests', 'Kept');
    $quote = usageAreaQuote($saved, $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['product_lines' => usageAreaLines(usageAreaCategory('opportunities'))])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_lines.0.product_category_id' => 'This product category is not used in this module.']);

    $this->patchJson("/api/request-management/{$quote->id}", ['product_lines' => [...usageAreaLines($saved), ...usageAreaLines($kept)]])
        ->assertOk();

    // REQUIREMENT CHANGED (spec 0389 rev.2): the exemption is checked on a mixed
    // card, the only kind that stays in the module once a category moves away.
    $saved->update(['usage_area' => 'opportunities']);
    $this->patchJson("/api/request-management/{$quote->id}", ['product_lines' => [...usageAreaLines($saved), ...usageAreaLines($kept)]])
        ->assertOk();
});

it('AC-006: the request grid cell refuses a NEW category of opportunities', function () {
    $actor = requestActor();
    $quote = usageAreaQuote(usageAreaCategory('all'), $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'product_categories',
        'value' => usageAreaLines(usageAreaCategory('opportunities')),
    ])->assertStatus(422);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'product_categories',
        'value' => usageAreaLines(usageAreaCategory('requests')),
    ])->assertOk();
});

it('the request grid column tells the editor its area', function () {
    Sanctum::actingAs(requestActor());

    $column = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'product_categories');

    expect($column['categoryUsageArea'])->toBe('requests');
});

// ---------------------------------------------------------------------------
// AC-004/AC-005/AC-006 — Opportunita'
// ---------------------------------------------------------------------------

/** @return array<string, mixed> */
function usageAreaOpportunityPayload(ProductCategory $category): array
{
    return [
        'name' => 'Opportunity',
        'registry_id' => Registry::factory()->create()->id,
        'supervisor_id' => User::factory()->create()->id,
        'product_lines' => usageAreaLines($category),
    ];
}

it('AC-004: an opportunity refuses a category of requests and accepts opportunities or all', function () {
    Sanctum::actingAs(usageAreaActor(['opportunities.create']));

    $this->postJson('/api/opportunities', usageAreaOpportunityPayload(usageAreaCategory('requests')))
        ->assertStatus(422)
        ->assertJsonValidationErrors('product_lines.0.product_category_id');

    $this->postJson('/api/opportunities', usageAreaOpportunityPayload(usageAreaCategory('opportunities')))->assertCreated();
    $this->postJson('/api/opportunities', usageAreaOpportunityPayload(usageAreaCategory('all')))->assertCreated();
});

it('AC-005/AC-006: an opportunity keeps its saved category; the cell refuses a NEW one of requests', function () {
    $category = usageAreaCategory('all');
    $shared = usageAreaCategory('all', 'Shared');
    Sanctum::actingAs(usageAreaActor(['opportunities.viewAny', 'opportunities.create', 'opportunities.update', 'opportunities.viewAll']));
    $payload = usageAreaOpportunityPayload($category);
    $payload['product_lines'] = [...usageAreaLines($category), ...usageAreaLines($shared)];
    $id = $this->postJson('/api/opportunities', $payload)->assertCreated()->json('data.id');

    // REQUIREMENT CHANGED (spec 0389 rev.2): a mixed card, still listed in Opportunita'.
    $category->update(['usage_area' => 'requests']);
    $this->patchJson("/api/opportunities/{$id}", ['product_lines' => $payload['product_lines']])->assertOk();

    $this->patchJson("/api/tables/opportunities/rows/{$id}", [
        'column' => 'product_category',
        'value' => usageAreaLines(usageAreaCategory('requests')),
    ])->assertStatus(422);

    $this->patchJson("/api/tables/opportunities/rows/{$id}", [
        'column' => 'product_category',
        'value' => usageAreaLines(usageAreaCategory('opportunities')),
    ])->assertOk();
});

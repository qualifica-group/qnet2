<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;

/**
 * `product_lines` (spec 0040, amendment rev.3) in the "create from lead"
 * flow: AC-102 (defaults resolver) + AC-103 (editable/removable, never
 * BR-2-locked). Split out of OpportunityFromLeadTest (file-size limit,
 * engineering.md §6) — reuses its `completeLead()`/`opportunityFromLeadActor()`/
 * `nonDerivableOpportunityFks()` helpers (globally declared, guarded with
 * `function_exists`).
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-102 — GET opportunity-defaults: product_lines derivation
// ---------------------------------------------------------------------------

it('opportunity-defaults: product_lines carries the campaign\'s EFFECTIVE business function + product category (AC-102)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.product_lines'))->toHaveCount(1);
    expect($response->json('data.product_lines.0.business_function.id'))->toBe($lead->campaign->business_function_id);
    expect($response->json('data.product_lines.0.product_category.id'))->toBe($lead->campaign->product_category_id);
});

it('opportunity-defaults: business_function/product_category come from the linked PROJECT\'s effective values (AC-061/AC-102)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create();

    $project = Project::factory()->create([
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);
    $campaign = Campaign::factory()->forProject($project)->create();

    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.product_lines.0.business_function.id'))->toBe($businessFunction->id);
    expect($response->json('data.product_lines.0.product_category.id'))->toBe($productCategory->id);
});

it('opportunity-defaults: product_lines is empty when the campaign has neither business function nor product category (AC-102)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $campaign = Campaign::factory()->create(['business_function_id' => null, 'product_category_id' => null]);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.product_lines'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-103 — create with lead_id: NOT auto-derived server-side, editable/removable
// ---------------------------------------------------------------------------

it('create with lead_id: product_lines is NOT auto-derived server-side — the client must submit it explicitly, and it is required (AC-102/AC-103, user directive 2026-07-17)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    // Drop the helper's default product_lines: this test proves the server does
    // not auto-derive it from the lead, so an omitted collection now 422s.
    $fks = Arr::except(nonDerivableOpportunityFks(), 'product_lines');
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'From lead, no product lines submitted',
        'lead_id' => $lead->id,
    ], $fks))->assertStatus(422)->assertJsonValidationErrors('product_lines');

    $this->assertDatabaseCount('opportunity_product_lines', 0);
});

it('create with lead_id: the client-submitted product_lines (matching the defaults prefill) persists and is editable but not clearable (AC-102/AC-103, user directive 2026-07-17)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    $otherBusinessFunction = BusinessFunction::factory()->create();
    $otherCategory = ProductCategory::factory()->create(['business_function_id' => $otherBusinessFunction->id]);
    Sanctum::actingAs($actor);

    // Helper FIRST so the explicit product_lines below overrides its default.
    $response = $this->postJson('/api/opportunities', array_merge(nonDerivableOpportunityFks(), [
        'name' => 'From lead, product lines submitted',
        'lead_id' => $lead->id,
        'product_lines' => [
            ['business_function_id' => $lead->campaign->business_function_id, 'product_category_id' => $lead->campaign->product_category_id],
        ],
        // Mandatory since 2026-07-23; from the submitted category, so it adds
        // no product line of its own.
        'products_of_interest' => [Product::factory()->create(['category_id' => $lead->campaign->product_category_id])->id],
    ]))->assertCreated();

    $opportunityId = $response->json('data.id');
    expect($response->json('data.product_lines'))->toHaveCount(1);

    // Editable — NOT a BR-2-locked field: a PATCH to a DIFFERENT valid row
    // succeeds (200), unlike a genuinely locked field which would 422.
    $this->patchJson("/api/opportunities/{$opportunityId}", [
        'product_lines' => [
            ['business_function_id' => $otherBusinessFunction->id, 'product_category_id' => $otherCategory->id],
        ],
        // User directive 2026-08-05: re-pointing the classification carries
        // the products of interest with it, else the coherence rule refuses a
        // set that would leave the persisted product uncovered.
        'products_of_interest' => [Product::factory()->create(['category_id' => $otherCategory->id])->id],
    ])->assertOk();
    $this->assertDatabaseCount('opportunity_product_lines', 1);

    // But NOT clearable to empty (user directive 2026-07-17: always >=1 row).
    $this->patchJson("/api/opportunities/{$opportunityId}", ['product_lines' => []])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');
    $this->assertDatabaseCount('opportunity_product_lines', 1);
});

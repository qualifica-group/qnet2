<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * User directive 2026-08-05: an opportunity whose products cannot proceed to
 * an offer must show neither the offers nor a way to create one. The FE gate
 * reads the additive `requires_quote` field OpportunityResource derives from
 * the product lines' categories — the branch-root-owned flag (spec 0070),
 * denormalised onto every node, so the row's own column is authoritative.
 */
uses(RefreshDatabase::class);

if (! function_exists('requiresQuoteOpportunityViewer')) {
    function requiresQuoteOpportunityViewer(): User
    {
        Permission::findOrCreate('opportunities.view');
        $user = User::factory()->create();
        $user->givePermissionTo('opportunities.view');

        return $user;
    }
}

it('exposes requires_quote = true when a product line category requires a quote', function (): void {
    $category = ProductCategory::factory()->create(['requires_quote' => true]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    Sanctum::actingAs(requiresQuoteOpportunityViewer());

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_quote', true);
});

it('exposes requires_quote = false when no product line category requires a quote', function (): void {
    $category = ProductCategory::factory()->create(['requires_quote' => false]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    Sanctum::actingAs(requiresQuoteOpportunityViewer());

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_quote', false);
});

it('exposes requires_quote = true when only ONE of several categories requires a quote', function (): void {
    $quotable = ProductCategory::factory()->create(['requires_quote' => true]);
    $plain = ProductCategory::factory()->create(['requires_quote' => false]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $quotable->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $plain->id]);
    Sanctum::actingAs(requiresQuoteOpportunityViewer());

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_quote', true);
});

it('exposes requires_quote = false for an opportunity with no product line (nothing quotable)', function (): void {
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(requiresQuoteOpportunityViewer());

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_quote', false)
        // No regression on the pre-existing shape.
        ->assertJsonPath('data.id', $opportunity->id);
});

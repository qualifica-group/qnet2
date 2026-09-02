<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Prodotti di interesse" on the Lead (spec 0094, D-5, AC-030..AC-036): the
 * SAME coherence rule as the Opportunity/request-management counterparts
 * (App\Services\Opportunities\ProductCategoryCoherence, LEAD_MESSAGE
 * template), applied against the lead's CAMPAIGN effective product lines —
 * see App\Services\Leads\LeadProductInterestWriter.
 */
uses(RefreshDatabase::class);

if (! function_exists('leadUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('leadCampaignCategory')) {
    /**
     * The ONE product category a standalone Campaign::factory() row already
     * covers — CampaignFactory::configure()'s afterCreating() attaches it.
     */
    function leadCampaignCategory(Campaign $campaign): ProductCategory
    {
        return $campaign->productLines()->first()->productCategory;
    }
}

it('create: products_of_interest from the campaign covered category persists; GET exposes it with its category (AC-030)', function () {
    $actor = leadUserWith(['create', 'view']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $category = leadCampaignCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'products_of_interest' => [$product->id],
    ])->assertCreated();

    $response->assertJsonPath('data.products_of_interest.0.id', $product->id)
        ->assertJsonPath('data.products_of_interest.0.product_category.id', $category->id);
    $leadId = $response->json('data.id');
    $this->assertDatabaseHas('lead_product', ['lead_id' => $leadId, 'product_id' => $product->id]);

    $this->getJson("/api/leads/{$leadId}")
        ->assertOk()
        ->assertJsonPath('data.products_of_interest.0.id', $product->id)
        ->assertJsonPath('data.products_of_interest.0.product_category.id', $category->id);
});

it('create: a product whose category is not covered by the campaign -> 422 with LEAD_MESSAGE (AC-031)', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $otherCategory = ProductCategory::factory()->create();
    $outsideProduct = Product::factory()->create(['category_id' => $otherCategory->id, 'name' => 'Fibra 1000']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'products_of_interest' => [$outsideProduct->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('products_of_interest')
        ->assertJsonFragment([
            'message' => 'These products of interest belong to a product category the lead does not carry: "Fibra 1000" ('.$otherCategory->name.'). Add that product category to the campaign, or remove the product.',
        ]);

    expect(Lead::count())->toBe(0);
});

it('coverage is exact-match only: a product in a CHILD of the covered category is refused (AC-032)', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $category = leadCampaignCategory($campaign);
    $childCategory = ProductCategory::factory()->childOf($category)->create();
    $childProduct = Product::factory()->create(['category_id' => $childCategory->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'products_of_interest' => [$childProduct->id],
    ])->assertStatus(422)->assertJsonValidationErrors('products_of_interest');

    expect(Lead::count())->toBe(0);
});

it('update: products_of_interest: [] clears the collection without error (AC-033)', function () {
    $actor = leadUserWith(['create', 'update']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $category = leadCampaignCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $lead = Lead::factory()->create(['registry_id' => $registry->id, 'campaign_id' => $campaign->id]);
    $lead->productsOfInterest()->sync([$product->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", ['products_of_interest' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.products_of_interest');

    expect($lead->fresh()->productsOfInterest)->toBeEmpty();
});

it('update: changing campaign_id while leaving persisted products uncovered -> 422 on campaign_id, nothing removed (AC-034)', function () {
    $actor = leadUserWith(['update']);
    $campaign = Campaign::factory()->create();
    $category = leadCampaignCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $lead->productsOfInterest()->sync([$product->id]);
    $newCampaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", ['campaign_id' => $newCampaign->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('campaign_id');

    expect($lead->fresh()->campaign_id)->toBe($campaign->id);
    expect($lead->fresh()->productsOfInterest->pluck('id')->all())->toBe([$product->id]);
});

it('update: changing campaign_id with a coherent products_of_interest in the same payload succeeds (AC-035)', function () {
    $actor = leadUserWith(['update']);
    $campaign = Campaign::factory()->create();
    $category = leadCampaignCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $lead->productsOfInterest()->sync([$product->id]);

    $newCampaign = Campaign::factory()->create();
    $newCategory = leadCampaignCategory($newCampaign);
    $newProduct = Product::factory()->create(['category_id' => $newCategory->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", [
        'campaign_id' => $newCampaign->id,
        'products_of_interest' => [$newProduct->id],
    ])->assertOk()->assertJsonCount(1, 'data.products_of_interest');

    expect($lead->fresh()->campaign_id)->toBe($newCampaign->id);
    expect($lead->fresh()->productsOfInterest->pluck('id')->all())->toBe([$newProduct->id]);
});

it('a lead with zero products of interest remains savable on every other field (AC-036)', function () {
    $actor = leadUserWith(['create', 'update']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
    ])->assertCreated();

    $response->assertJsonPath('data.products_of_interest', []);
    $leadId = $response->json('data.id');

    $this->patchJson("/api/leads/{$leadId}", ['notes' => 'Follow up'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Follow up')
        ->assertJsonPath('data.products_of_interest', []);
});

<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Derived product lines, transferred products of interest and the generated
 * Offerta (spec 0094, AC-060..AC-067) — split out of LeadConversionTest
 * (file-size limit, engineering.md §6). Reuses its `leadConversionActor()`/
 * `convertibleLeadFixture()` helpers (globally declared, guarded with
 * `function_exists`). AC-068/AC-069 (the bulk action) live in
 * BulkLeadConversionTest, that action's own file.
 */
uses(RefreshDatabase::class);

if (! function_exists('campaignWithLines')) {
    /**
     * A standalone campaign carrying EXACTLY the given (business_function,
     * product_category) pairs — CampaignFactory's own auto-created coherent
     * row is dropped first, then replaced with $pairs.
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $pairs
     */
    function campaignWithLines(array $pairs): Campaign
    {
        $campaign = Campaign::factory()->create();
        $campaign->productLines()->delete();

        foreach ($pairs as $pair) {
            $campaign->productLines()->create($pair);
        }

        return $campaign;
    }
}

if (! function_exists('coherentCategoryPair')) {
    /**
     * A fresh (business_function, product_category) pair, the category's OWN
     * business_function_id matching it (satisfies
     * CategoryHierarchy::effectiveBusinessFunction()).
     *
     * @param  array<string, mixed>  $categoryAttributes
     * @return array{business_function_id: int, product_category_id: int}
     */
    function coherentCategoryPair(array $categoryAttributes = []): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(array_merge(
            ['business_function_id' => $businessFunction->id],
            $categoryAttributes,
        ));

        return ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id];
    }
}

it('AC-060: a campaign with 3 product lines converts into an Opportunity with the same 3 lines', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $pairs = [coherentCategoryPair(), coherentCategoryPair(), coherentCategoryPair()];
    $campaign = campaignWithLines($pairs);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'convert_to_opportunity' => true,
    ])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('productLines');

    expect($opportunity->productLines)->toHaveCount(3);
    expect($opportunity->productLines->pluck('product_category_id')->sort()->values()->all())
        ->toBe(collect($pairs)->pluck('product_category_id')->sort()->values()->all());
});

it('AC-061: the lead products of interest are transferred to the Opportunity', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $pair = coherentCategoryPair();
    $campaign = campaignWithLines([$pair]);
    $product = Product::factory()->create(['category_id' => $pair['product_category_id']]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'convert_to_opportunity' => true,
        'products_of_interest' => [$product->id],
    ])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('productsOfInterest');

    expect($opportunity->productsOfInterest->pluck('id')->all())->toBe([$product->id]);
});

it('AC-062: at least one product of interest generates ONE Offerta with one REVENUE line per product', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $pair = coherentCategoryPair();
    $campaign = campaignWithLines([$pair]);
    $vatRate = VatRate::factory()->create();
    $productA = Product::factory()->create(['category_id' => $pair['product_category_id'], 'price' => 100, 'vat_rate_id' => $vatRate->id]);
    $productB = Product::factory()->create(['category_id' => $pair['product_category_id'], 'price' => 200, 'vat_rate_id' => $vatRate->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'convert_to_opportunity' => true,
        'products_of_interest' => [$productA->id, $productB->id],
    ])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(1);

    $quote = Quote::where('opportunity_id', $opportunity->id)->firstOrFail();
    $quote->load('offerLines');

    expect($quote->offerLines)->toHaveCount(2);
    expect($quote->offerLines->pluck('product_id')->sort()->values()->all())
        ->toBe(collect([$productA->id, $productB->id])->sort()->values()->all());

    $lineA = $quote->offerLines->firstWhere('product_id', $productA->id);
    expect((float) $lineA->quantity)->toBe(1.0);
    expect((float) $lineA->unit_price)->toBe(100.0);
    expect($lineA->vat_rate_id)->toBe($vatRate->id);

    expect($quote->offerLines->pluck('sort_order')->sort()->values()->all())->toBe([0, 1]);
});

it('AC-063: the generated offer lines freeze the unit of measure from the product', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $pair = coherentCategoryPair();
    $campaign = campaignWithLines([$pair]);
    $product = Product::factory()->create(['category_id' => $pair['product_category_id']]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'convert_to_opportunity' => true,
        'products_of_interest' => [$product->id],
    ])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $line = Quote::where('opportunity_id', $opportunity->id)->firstOrFail()->offerLines()->firstOrFail();

    expect($line->unit_of_measure_id)->toBe($product->unit_of_measure_id);
});

it('AC-064: the generated Offerta carries a QUO- code, an initial workflow status and computed aggregates', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $pair = coherentCategoryPair();
    $campaign = campaignWithLines([$pair]);
    $product = Product::factory()->create(['category_id' => $pair['product_category_id'], 'price' => 50]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'convert_to_opportunity' => true,
        'products_of_interest' => [$product->id],
    ])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $quote = Quote::where('opportunity_id', $opportunity->id)->firstOrFail();

    expect($quote->code)->toStartWith('QUO-');
    expect($quote->quote_workflow_status_id)->not->toBeNull();
    expect((float) $quote->revenue_net)->toBe(50.0);
});

it('AC-065: zero products of interest converts the lead without generating an Offerta', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(0);
});

it('AC-066: a duplicated product of interest produces a single offer line', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $pair = coherentCategoryPair();
    $campaign = campaignWithLines([$pair]);
    $product = Product::factory()->create(['category_id' => $pair['product_category_id']]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'convert_to_opportunity' => true,
        // A repeated id in the submitted payload: the pivot's own unique
        // constraint (AC-005) already collapses it to one row, and the
        // offer must not duplicate it either.
        'products_of_interest' => [$product->id, $product->id],
    ])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $quote = Quote::where('opportunity_id', $opportunity->id)->firstOrFail();

    expect($quote->offerLines)->toHaveCount(1);
});

it('AC-067: a single-managed derivation with 2+ products of interest -> 422, no Opportunity or Offerta persisted', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $pair = coherentCategoryPair(['management_mode' => CategoryManagementMode::Single]);
    $campaign = campaignWithLines([$pair]);
    $productA = Product::factory()->create(['category_id' => $pair['product_category_id']]);
    $productB = Product::factory()->create(['category_id' => $pair['product_category_id']]);
    Sanctum::actingAs($actor);

    $leadCountBefore = Lead::count();
    $opportunityCountBefore = Opportunity::count();
    $quoteCountBefore = Quote::count();

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'convert_to_opportunity' => true,
        'products_of_interest' => [$productA->id, $productB->id],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines');

    expect(Lead::count())->toBe($leadCountBefore);
    expect(Opportunity::count())->toBe($opportunityCountBefore);
    expect(Quote::count())->toBe($quoteCountBefore);
});

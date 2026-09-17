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
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0140: the "Converti lead" row action opens the prefilled Opportunity
 * form, which saves through POST /api/opportunities with `lead_id`. That path
 * must generate the collegata Offerta exactly like the import / creation
 * checkbox / bulk conversion (spec 0094 D-3, spec 0102 D-1).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('opportunities.create');
    Permission::findOrCreate('leads.view');

    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo(['opportunities.create', 'leads.view']);
    Sanctum::actingAs($this->actor);

    $this->lead = Lead::factory()->create([
        'campaign_id' => Campaign::factory()->create()->id,
        'registry_id' => Registry::factory()->create()->id,
    ]);
});

if (! function_exists('offerFormProductLine')) {
    /**
     * @return array{product_lines: array<int, array{business_function_id: int, product_category_id: int}>, category_id: int}
     */
    function offerFormProductLine(CategoryManagementMode $mode = CategoryManagementMode::Multiple): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create([
            'business_function_id' => $businessFunction->id,
            'management_mode' => $mode,
        ]);

        return [
            'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
            'category_id' => $category->id,
        ];
    }
}

it('creating an opportunity from a lead generates ONE Offerta with one REVENUE line per product of interest', function () {
    $line = offerFormProductLine();
    $vatRate = VatRate::factory()->create();
    $products = Product::factory()->count(2)->create(['category_id' => $line['category_id'], 'price' => 100, 'vat_rate_id' => $vatRate->id]);

    $response = $this->postJson('/api/opportunities', [
        'lead_id' => $this->lead->id,
        'product_lines' => $line['product_lines'],
        'products_of_interest' => $products->pluck('id')->all(),
    ])->assertCreated();

    $quotes = Quote::with('offerLines')->where('opportunity_id', $response->json('data.id'))->get();

    expect($quotes)->toHaveCount(1);
    expect($quotes->first()->offerLines->pluck('product_id')->sort()->values()->all())
        ->toBe($products->pluck('id')->sort()->values()->all());
    expect($response->json('data.quotes_count'))->toBe(1);
});

it('creating an opportunity from a lead without products of interest generates an empty Offerta', function () {
    $line = offerFormProductLine();

    $response = $this->postJson('/api/opportunities', [
        'lead_id' => $this->lead->id,
        'product_lines' => $line['product_lines'],
    ])->assertCreated();

    $quotes = Quote::with('offerLines')->where('opportunity_id', $response->json('data.id'))->get();

    expect($quotes)->toHaveCount(1);
    expect($quotes->first()->offerLines)->toHaveCount(0);
});

it('creating an opportunity without a lead does not generate an Offerta', function () {
    $line = offerFormProductLine();

    $response = $this->postJson('/api/opportunities', [
        'registry_id' => Registry::factory()->create()->id,
        'product_lines' => $line['product_lines'],
    ])->assertCreated();

    expect(Quote::where('opportunity_id', $response->json('data.id'))->count())->toBe(0);
});

it('a single-managed classification with 2+ products of interest from a lead -> 422, nothing persisted', function () {
    $line = offerFormProductLine(CategoryManagementMode::Single);
    $products = Product::factory()->count(2)->create(['category_id' => $line['category_id']]);

    $this->postJson('/api/opportunities', [
        'lead_id' => $this->lead->id,
        'product_lines' => $line['product_lines'],
        'products_of_interest' => $products->pluck('id')->all(),
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines');

    expect(Opportunity::where('lead_id', $this->lead->id)->exists())->toBeFalse();
    expect(Quote::count())->toBe(0);
});

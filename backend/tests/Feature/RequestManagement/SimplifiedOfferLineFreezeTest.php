<?php

declare(strict_types=1);

use App\Enums\CategoryManagementMode;
use App\Enums\QuoteLineType;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0114, D-4/D-5/D-6: server-side freeze of quantity/unit-price/VAT-rate
// on a `simplified_offer_line` classification, across the module's two write
// choke points (RequestCreationService, RequestOfferLineWriter) and its three
// channels (create form, work panel, inline grid cell) — AC-008..016. The
// column itself, its inheritance and the UI it drives are covered elsewhere
// (MT-1/MT-2/frontend); this file only exercises the freeze.

uses(RefreshDatabase::class);

if (! function_exists('simplifiedLineActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function simplifiedLineActor(array $abilities = ['view', 'update', 'create', 'viewAll']): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('simplifiedLineCategory')) {
    /** A product category with its own business function, `simplified_offer_line` set as requested. */
    function simplifiedLineCategory(bool $simplified = true): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'management_mode' => CategoryManagementMode::Multiple,
            'simplified_offer_line' => $simplified,
        ]);
    }
}

if (! function_exists('simplifiedLineRequest')) {
    /** A request (Offerta) whose Opportunity carries one product line on $category. */
    function simplifiedLineRequest(User $operator, ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
        OpportunityProductLine::factory()->for($opportunity)->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

if (! function_exists('simplifiedLineProduct')) {
    function simplifiedLineProduct(ProductCategory $category, ?float $price, ?int $vatRateId): Product
    {
        return Product::factory()->create([
            'category_id' => $category->id,
            'price' => $price,
            'vat_rate_id' => $vatRateId,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-008/AC-009 — creation (RequestCreationService)
// ---------------------------------------------------------------------------

it('AC-008: create freezes quantity/unit-price/VAT-rate from the product, ignoring the submitted values', function () {
    $actor = simplifiedLineActor(['create']);
    $category = simplifiedLineCategory();
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $wrongVatRate = VatRate::factory()->create(['rate' => 10]);
    $product = simplifiedLineProduct($category, 150.00, $vatRate->id);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 7,
            'unit_price' => 999,
            'vat_rate_id' => $wrongVatRate->id,
        ]],
    ])->assertCreated();

    $line = Quote::query()->sole()->offerLines()->sole();
    expect((float) $line->quantity)->toBe(1.0)
        ->and((float) $line->unit_price)->toBe(150.0)
        ->and($line->vat_rate_id)->toBe($vatRate->id);
});

it('AC-009: a product with a NULL price freezes unit-price to 0, no 422', function () {
    $actor = simplifiedLineActor(['create']);
    $category = simplifiedLineCategory();
    $product = simplifiedLineProduct($category, null, null);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 0,
        ]],
    ])->assertCreated();

    $line = Quote::query()->sole()->offerLines()->sole();
    expect((float) $line->unit_price)->toBe(0.0)
        ->and($line->vat_rate_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-010/AC-011/AC-012 — work panel PATCH (RequestOfferLineWriter)
// ---------------------------------------------------------------------------

it('AC-010: PATCH adds a NEW row (no id) — frozen from its product, submitted values ignored', function () {
    $actor = simplifiedLineActor();
    $category = simplifiedLineCategory();
    $quote = simplifiedLineRequest($actor, $category);
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $product = simplifiedLineProduct($category, 200.00, $vatRate->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 9,
            'unit_price' => 1,
        ]],
    ])->assertOk();

    $line = $quote->offerLines()->sole();
    expect((float) $line->quantity)->toBe(1.0)
        ->and((float) $line->unit_price)->toBe(200.0)
        ->and($line->vat_rate_id)->toBe($vatRate->id);
});

it('AC-011: a resubmit with the SAME id and product leaves the persisted line untouched (grandfathered, D-6)', function () {
    $actor = simplifiedLineActor();
    $category = simplifiedLineCategory();
    $quote = simplifiedLineRequest($actor, $category);
    $originalVat = VatRate::factory()->create(['rate' => 22]);
    $product = simplifiedLineProduct($category, 150.00, $originalVat->id);
    // A row persisted with values that do NOT match what a fresh freeze from
    // $product would produce (quantity: 3, not 1) — simulates a row this
    // module already froze earlier under a different product price, or one
    // grandfathered before the setting existed: only a genuine id+product
    // resubmit proves nothing was silently re-synced.
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'line_type' => QuoteLineType::Revenue,
        'quantity' => 3,
        'unit_price' => 150.00,
        'vat_rate_id' => $originalVat->id,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'id' => $line->id,
            'product_id' => $product->id,
            'quantity' => 9,
            'unit_price' => 1,
        ]],
    ])->assertOk();

    $line->refresh();
    expect((float) $line->quantity)->toBe(3.0)
        ->and((float) $line->unit_price)->toBe(150.0)
        ->and($line->vat_rate_id)->toBe($originalVat->id);
});

it('AC-012: a resubmit with the SAME id but a DIFFERENT product re-freezes on the new product', function () {
    $actor = simplifiedLineActor();
    $category = simplifiedLineCategory();
    $quote = simplifiedLineRequest($actor, $category);
    $originalVat = VatRate::factory()->create(['rate' => 22]);
    $newVat = VatRate::factory()->create(['rate' => 10]);
    $oldProduct = simplifiedLineProduct($category, 150.00, $originalVat->id);
    $newProduct = simplifiedLineProduct($category, 80.00, $newVat->id);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $oldProduct->id,
        'line_type' => QuoteLineType::Revenue,
        'quantity' => 3,
        'unit_price' => 150.00,
        'vat_rate_id' => $originalVat->id,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'id' => $line->id,
            'product_id' => $newProduct->id,
            'quantity' => 9,
            'unit_price' => 1,
        ]],
    ])->assertOk();

    $line->refresh();
    expect($line->product_id)->toBe($newProduct->id)
        ->and((float) $line->quantity)->toBe(1.0)
        ->and((float) $line->unit_price)->toBe(80.0)
        ->and($line->vat_rate_id)->toBe($newVat->id);
});

// ---------------------------------------------------------------------------
// AC-013 — the same normalization through the generic inline cell engine
// ---------------------------------------------------------------------------

it('AC-013: the inline grid cell inherits the same normalization as the panel', function () {
    $actor = simplifiedLineActor(['viewAny', 'view', 'update', 'viewAll']);
    $category = simplifiedLineCategory();
    $quote = simplifiedLineRequest($actor, $category);
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $product = simplifiedLineProduct($category, 200.00, $vatRate->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $product->id, 'quantity' => 9, 'unit_price' => 1]],
    ])->assertOk();

    $line = $quote->offerLines()->sole();
    expect((float) $line->quantity)->toBe(1.0)
        ->and((float) $line->unit_price)->toBe(200.0)
        ->and($line->vat_rate_id)->toBe($vatRate->id);
});

// ---------------------------------------------------------------------------
// AC-014 — a NON-simplified classification is a pure no-op (regression)
// ---------------------------------------------------------------------------

it('AC-014: a non-simplified classification persists the submitted values as-is', function () {
    $actor = simplifiedLineActor();
    $category = simplifiedLineCategory(simplified: false);
    $quote = simplifiedLineRequest($actor, $category);
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $product = simplifiedLineProduct($category, 150.00, $vatRate->id);
    $otherVat = VatRate::factory()->create(['rate' => 10]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 7,
            'unit_price' => 999,
            'vat_rate_id' => $otherVat->id,
        ]],
    ])->assertOk();

    $line = $quote->offerLines()->sole();
    expect((float) $line->quantity)->toBe(7.0)
        ->and((float) $line->unit_price)->toBe(999.0)
        ->and($line->vat_rate_id)->toBe($otherVat->id);
});

// ---------------------------------------------------------------------------
// AC-015 — the Offerte module stays neutral (D-3), even on a simplified category
// ---------------------------------------------------------------------------

it('AC-015: PATCH /api/quotes/{quote} never normalizes, even on a simplified category', function () {
    $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'];
    foreach ($abilities as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('quotes.update');
    $category = simplifiedLineCategory();
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $product = simplifiedLineProduct($category, 150.00, $vatRate->id);
    $otherVat = VatRate::factory()->create(['rate' => 10]);
    $quote = Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", [
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 999,
            'vat_rate_id' => $otherVat->id,
        ]],
    ])->assertOk();

    $line = $quote->offerLines()->sole();
    expect((float) $line->quantity)->toBe(4.0)
        ->and((float) $line->unit_price)->toBe(999.0)
        ->and($line->vat_rate_id)->toBe($otherVat->id);
});

// ---------------------------------------------------------------------------
// AC-016 — aggregates are computed on the FROZEN values, not the submitted ones
// ---------------------------------------------------------------------------

it('AC-016: net/VAT/total and the offer aggregates are computed on the frozen values', function () {
    $actor = simplifiedLineActor(['create']);
    $category = simplifiedLineCategory();
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $product = simplifiedLineProduct($category, 150.00, $vatRate->id);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 7,
            'unit_price' => 999,
        ]],
    ])->assertCreated();

    $quote = Quote::query()->sole();
    $line = $quote->offerLines()->sole();

    // Frozen: quantity 1 * unit_price 150.00, 22% VAT — never 7 * 999.
    expect((float) $line->net_amount)->toBe(150.0)
        ->and((float) $line->vat_amount)->toBe(33.0)
        ->and((float) $line->total_amount)->toBe(183.0)
        ->and((float) $quote->fresh()->revenue_net)->toBe(150.0)
        ->and((float) $response->json('data.offer_lines.0.net_amount'))->toBe(150.0);
});

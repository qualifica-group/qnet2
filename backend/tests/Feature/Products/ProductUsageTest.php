<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Enums\ProductUsage;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\Quotes\ProductOfferLineResolver;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0142: a product's `usages` (Sellable / Usable as cost) decides which
 * Offerta tab may pick it — the for-select filter (D-4) and the write-side
 * guard in QuoteLineWriter (D-5), with persisted rows exempt.
 */
uses(RefreshDatabase::class);

if (! function_exists('productUsageActor')) {
    function productUsageActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['products.viewAny', 'products.view', 'products.create', 'products.update']);

        return $user;
    }
}

if (! function_exists('productUsagePayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function productUsagePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Widget',
            'cost' => 10,
            'price' => 20,
            'product_type' => 'SERVICE',
            'category_id' => ProductCategory::factory()->create()->id,
        ], $overrides);
    }
}

if (! function_exists('productUsageQuoteProduct')) {
    /**
     * A product whose category resolves a business function, so a REVENUE
     * line never trips the opportunity coverage 422 (spec 0065, D-7).
     *
     * @param  array<int, ProductUsage>  $usages
     */
    function productUsageQuoteProduct(array $usages): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id, 'usages' => $usages]);
    }
}

if (! function_exists('productUsageLine')) {
    function productUsageLine(Product $product, ?int $id = null): QuoteLineData
    {
        return new QuoteLineData(id: $id, productId: $product->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null);
    }
}

if (! function_exists('productUsageCreateQuote')) {
    /**
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    function productUsageCreateQuote(?array $offerLines, ?array $costLines): Quote
    {
        QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

        return app(QuoteService::class)->create(new CreateQuoteData(
            code: null,
            title: 'Offerta',
            opportunityId: Opportunity::factory()->create()->id,
            workflowStatusId: null,
            note: null,
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: null,
            offerLines: $offerLines,
            costLines: $costLines,
        ), User::factory()->create());
    }
}

// AC-001 — the migration.

it('AC-001: the migration backfills existing products to Sellable only and its rollback drops the column', function () {
    $migration = require database_path('migrations/2026_09_18_130000_add_usages_to_products_table.php');
    $product = Product::factory()->costOnly()->create();

    $migration->down();
    expect(Schema::hasColumn('products', 'usages'))->toBeFalse();

    $migration->up();
    expect($product->fresh()->usages->all())->toBe([ProductUsage::Sale]);
});

// AC-002 / AC-003 — the product write path.

it('AC-002: create without usages defaults to Sellable only', function () {
    Sanctum::actingAs(productUsageActor());

    $response = $this->postJson('/api/products', productUsagePayload())->assertCreated();

    $response->assertJsonPath('data.usages', ['SALE']);
    expect(Product::findOrFail($response->json('data.id'))->isUsableAs(ProductUsage::Cost))->toBeFalse();
});

it('AC-002: create persists an explicit usage set', function (array $usages) {
    Sanctum::actingAs(productUsageActor());

    $this->postJson('/api/products', productUsagePayload(['usages' => $usages]))
        ->assertCreated()
        ->assertJsonPath('data.usages', $usages);
})->with([
    'cost only' => [['COST']],
    'both' => [['SALE', 'COST']],
]);

it('AC-002: create rejects an empty, unknown or duplicated usage set', function (array $usages) {
    Sanctum::actingAs(productUsageActor());

    $this->postJson('/api/products', productUsagePayload(['usages' => $usages]))
        ->assertUnprocessable();
})->with([
    'empty' => [[]],
    'unknown' => [['RENT']],
    'duplicated' => [['SALE', 'SALE']],
]);

it('AC-003: patch updates usages, and a patch without usages preserves them', function () {
    Sanctum::actingAs(productUsageActor());
    $product = Product::factory()->saleOnly()->create();

    $this->patchJson("/api/products/{$product->id}", ['usages' => ['COST']])
        ->assertOk()
        ->assertJsonPath('data.usages', ['COST']);

    $this->patchJson("/api/products/{$product->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.usages', ['COST']);
});

// AC-004 — the for-select filter.

it('AC-004: for-select usage narrows items and total to the products usable on that tab', function () {
    Sanctum::actingAs(productUsageActor());
    $saleOnly = Product::factory()->saleOnly()->create(['name' => 'A sale']);
    $costOnly = Product::factory()->costOnly()->create(['name' => 'B cost']);
    $both = Product::factory()->create(['name' => 'C both']);

    $sale = $this->getJson('/api/products/for-select?usage=SALE')->assertOk();
    expect(array_column($sale->json('items'), 'id'))->toBe([$saleOnly->id, $both->id])
        ->and($sale->json('pagination.total'))->toBe(2);

    $cost = $this->getJson('/api/products/for-select?usage=COST')->assertOk();
    expect(array_column($cost->json('items'), 'id'))->toBe([$costOnly->id, $both->id])
        ->and($cost->json('pagination.total'))->toBe(2);

    $this->getJson('/api/products/for-select')->assertOk()->assertJsonPath('pagination.total', 3);
    $this->getJson('/api/products/for-select?usage=RENT')->assertUnprocessable();
});

it('AC-004: ids[] hydrates a product outside the usage filter without inflating total', function () {
    Sanctum::actingAs(productUsageActor());
    $saleOnly = Product::factory()->saleOnly()->create();
    Product::factory()->costOnly()->create();

    $response = $this->getJson("/api/products/for-select?usage=COST&ids[]={$saleOnly->id}")->assertOk();

    expect(array_column($response->json('items'), 'id'))->toContain($saleOnly->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

// AC-005 / AC-006 — the write-side guard in QuoteLineWriter.

it('AC-005: a new REVENUE line with a cost-only product is rejected on its row', function () {
    $costOnly = productUsageQuoteProduct([ProductUsage::Cost]);

    expect(fn () => productUsageCreateQuote([productUsageLine($costOnly)], null))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('offer_lines.0.product_id');
        });
});

it('AC-005: a new COST line with a sale-only product is rejected on its row', function () {
    $saleOnly = productUsageQuoteProduct([ProductUsage::Sale]);
    $costOnly = productUsageQuoteProduct([ProductUsage::Cost]);

    expect(fn () => productUsageCreateQuote(
        [productUsageLine($saleOnly)],
        [productUsageLine($costOnly), productUsageLine($saleOnly)],
    ))->toThrow(function (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('cost_lines.1.product_id')
            ->not->toHaveKey('cost_lines.0.product_id');
    });
});

it('AC-005: a product with both usages is accepted on both tabs', function () {
    $both = productUsageQuoteProduct([ProductUsage::Sale, ProductUsage::Cost]);

    $quote = productUsageCreateQuote([productUsageLine($both)], [productUsageLine($both)]);

    expect($quote->offerLines()->count())->toBe(1)
        ->and($quote->costLines()->count())->toBe(1);
});

it('AC-006: a persisted row resubmitting its own product stays valid after the product loses that usage', function () {
    $product = productUsageQuoteProduct([ProductUsage::Sale, ProductUsage::Cost]);
    $quote = productUsageCreateQuote([productUsageLine($product)], [productUsageLine($product)]);
    $costLineId = $quote->costLines()->value('id');

    $product->update(['usages' => [ProductUsage::Sale]]);

    app(QuoteService::class)->update($quote, new UpdateQuoteData(
        costLines: [productUsageLine($product, $costLineId)],
    ), User::factory()->create());

    expect($quote->costLines()->value('product_id'))->toBe($product->id);
});

it('AC-006: changing a persisted row to a product not usable on its tab is rejected', function () {
    $both = productUsageQuoteProduct([ProductUsage::Sale, ProductUsage::Cost]);
    $saleOnly = productUsageQuoteProduct([ProductUsage::Sale]);
    $quote = productUsageCreateQuote([productUsageLine($both)], [productUsageLine($both)]);
    $costLineId = $quote->costLines()->value('id');

    expect(fn () => app(QuoteService::class)->update($quote, new UpdateQuoteData(
        costLines: [productUsageLine($saleOnly, $costLineId)],
    ), User::factory()->create()))->toThrow(ValidationException::class);

    expect($quote->costLines()->value('product_id'))->toBe($both->id);
});

// AC-007 — Lead conversion.

it('AC-007: the Lead conversion offer lines skip products of interest that are not sellable', function () {
    $sellable = Product::factory()->saleOnly()->create();
    $costOnly = Product::factory()->costOnly()->create();

    $lines = app(ProductOfferLineResolver::class)->resolve([$costOnly->id, $sellable->id]);

    expect(array_map(static fn (QuoteLineData $line): int => $line->productId, $lines))->toBe([$sellable->id]);
});

// AC-010 — the products grid column (filterable, sortable).

if (! function_exists('productUsageGridRows')) {
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    function productUsageGridRows(array $payload): array
    {
        return collect(test()->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25, ...$payload])
            ->assertOk()
            ->json('items'))
            ->pluck('name')
            ->all();
    }
}

it('AC-010: the grid exposes usages as a tags column localized through product_usage', function () {
    Sanctum::actingAs(productUsageActor());
    Product::factory()->create(['name' => 'Both', 'usages' => [ProductUsage::Cost, ProductUsage::Sale]]);

    $column = collect($this->getJson('/api/tables/products/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'usages');
    expect($column)->toMatchArray(['type' => 'tags', 'sortable' => true, 'filterable' => true, 'filterType' => 'set', 'enumKey' => 'product_usage']);

    // Stored and projected in canonical case order, whatever order it was set in.
    $row = $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');
    expect($row['usages'])->toBe(['SALE', 'COST']);
});

it('AC-010: the set filter keeps the products carrying ANY selected usage and ignores unknown values', function () {
    Sanctum::actingAs(productUsageActor());
    Product::factory()->saleOnly()->create(['name' => 'Sale']);
    Product::factory()->costOnly()->create(['name' => 'Cost']);
    Product::factory()->create(['name' => 'Both']);
    $sort = ['sortModel' => [['colId' => 'name', 'sort' => 'asc']]];

    expect(productUsageGridRows([...$sort, 'filterModel' => ['usages' => ['filterType' => 'set', 'values' => ['COST']]]]))
        ->toBe(['Both', 'Cost'])
        ->and(productUsageGridRows([...$sort, 'filterModel' => ['usages' => ['filterType' => 'set', 'values' => ['SALE', 'COST']]]]))
        ->toBe(['Both', 'Cost', 'Sale'])
        ->and(productUsageGridRows([...$sort, 'filterModel' => ['usages' => ['filterType' => 'set', 'values' => ['RENT']]]]))
        ->toBe(['Both', 'Cost', 'Sale']);
});

it('AC-010: sorting groups identical sets: cost only, both, sellable only (reversed when descending)', function () {
    Sanctum::actingAs(productUsageActor());
    Product::factory()->saleOnly()->create(['name' => 'Sale']);
    Product::factory()->costOnly()->create(['name' => 'Cost']);
    Product::factory()->create(['name' => 'Both', 'usages' => [ProductUsage::Cost, ProductUsage::Sale]]);

    expect(productUsageGridRows(['sortModel' => [['colId' => 'usages', 'sort' => 'asc']]]))->toBe(['Cost', 'Both', 'Sale'])
        ->and(productUsageGridRows(['sortModel' => [['colId' => 'usages', 'sort' => 'desc']]]))->toBe(['Sale', 'Both', 'Cost']);
});

it('AC-010: the values endpoint lists only the usages present among the scoped products', function () {
    Sanctum::actingAs(productUsageActor());
    Product::factory()->saleOnly()->create();

    $this->postJson('/api/tables/products/values', ['columnId' => 'usages'])
        ->assertOk()
        ->assertJsonPath('data.values', ['SALE']);

    Product::factory()->costOnly()->create();

    $this->postJson('/api/tables/products/values', ['columnId' => 'usages'])
        ->assertOk()
        ->assertJsonPath('data.values', ['SALE', 'COST']);
});

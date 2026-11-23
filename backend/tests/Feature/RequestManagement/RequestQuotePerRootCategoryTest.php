<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Support\QuoteWorkflows\QuoteClassificationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0411: a request created with product lines of N root categories
// ("categorie madri") produces N offers on the same opportunity, each bound to
// its root; a bound offer reads and edits only its root's lines.

uses(RefreshDatabase::class);

if (! function_exists('rootQuoteActor')) {
    function rootQuoteActor(): User
    {
        $user = User::factory()->create();

        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("request-management.{$ability}"));
        }

        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("opportunities.{$ability}"));
        }

        return $user;
    }
}

if (! function_exists('rootQuoteTree')) {
    /**
     * Two roots (ISO, APL) with two and one selectable children, plus a product on each child.
     *
     * @return array{iso: ProductCategory, apl: ProductCategory, iso9001: ProductCategory, iso14001: ProductCategory, tirocinio: ProductCategory, isoProduct: Product, aplProduct: Product}
     */
    function rootQuoteTree(): array
    {
        $function = BusinessFunction::factory()->create();
        $iso = ProductCategory::factory()->create(['name' => 'ISO', 'business_function_id' => $function->id]);
        $apl = ProductCategory::factory()->create(['name' => 'APL', 'business_function_id' => $function->id]);
        $iso9001 = ProductCategory::factory()->childOf($iso)->create(['name' => 'ISO 9001', 'business_function_id' => $function->id]);
        $iso14001 = ProductCategory::factory()->childOf($iso)->create(['name' => 'ISO 14001', 'business_function_id' => $function->id]);
        $tirocinio = ProductCategory::factory()->childOf($apl)->create(['name' => 'Tirocinio', 'business_function_id' => $function->id]);

        return [
            'iso' => $iso,
            'apl' => $apl,
            'iso9001' => $iso9001,
            'iso14001' => $iso14001,
            'tirocinio' => $tirocinio,
            'isoProduct' => Product::factory()->create(['category_id' => $iso9001->id]),
            'aplProduct' => Product::factory()->create(['category_id' => $tirocinio->id]),
        ];
    }
}

if (! function_exists('createRootQuoteRequest')) {
    /**
     * @param  array<int, ProductCategory>  $categories
     * @param  array<string, mixed>  $extra
     * @return array<int, int> the created quote ids
     */
    function createRootQuoteRequest(array $categories, array $extra = []): array
    {
        $response = test()->postJson('/api/request-management', [
            'registry_id' => Registry::factory()->create()->id,
            'product_lines' => array_map(static fn (ProductCategory $category): array => ['product_category_id' => $category->id], $categories),
            'source_id' => aSourceId(),
            ...$extra,
        ])->assertCreated();

        return $response->json('data.created_quote_ids');
    }
}

it('AC-001: lines of two roots create two offers on the same opportunity, one bound to each root', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());

    $ids = createRootQuoteRequest([$tree['iso9001'], $tree['tirocinio'], $tree['iso14001']]);

    $quotes = Quote::query()->findMany($ids)->keyBy('id');
    expect($ids)->toHaveCount(2)
        ->and($quotes[$ids[0]]->root_product_category_id)->toBe($tree['iso']->id)
        ->and($quotes[$ids[1]]->root_product_category_id)->toBe($tree['apl']->id)
        ->and($quotes[$ids[0]]->opportunity_id)->toBe($quotes[$ids[1]]->opportunity_id)
        ->and(OpportunityProductLine::query()->where('opportunity_id', $quotes[$ids[0]]->opportunity_id)->count())->toBe(3);
});

it('AC-002: lines of a single root create one bound offer', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());

    $ids = createRootQuoteRequest([$tree['iso9001'], $tree['iso14001']]);

    expect($ids)->toHaveCount(1)
        ->and(Quote::findOrFail($ids[0])->root_product_category_id)->toBe($tree['iso']->id);
});

it('AC-003: each offer receives only the offer rows of its own root', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());

    $ids = createRootQuoteRequest([$tree['iso9001'], $tree['tirocinio']], [
        'offer_lines' => [
            ['product_id' => $tree['aplProduct']->id, 'quantity' => 1, 'unit_price' => 50],
            ['product_id' => $tree['isoProduct']->id, 'quantity' => 2, 'unit_price' => 100],
        ],
    ]);

    expect(Quote::findOrFail($ids[0])->offerLines()->pluck('product_id')->all())->toBe([$tree['isoProduct']->id])
        ->and(Quote::findOrFail($ids[1])->offerLines()->pluck('product_id')->all())->toBe([$tree['aplProduct']->id]);
});

it('AC-003: an offer row outside every root of the request is refused, nothing is created', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'product_lines' => [['product_category_id' => $tree['iso9001']->id]],
        'source_id' => aSourceId(),
        'offer_lines' => [['product_id' => $tree['aplProduct']->id, 'quantity' => 1, 'unit_price' => 50]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['offer_lines.0.product_id']);

    expect(Quote::count())->toBe(0);
});

it('AC-004: a bound offer counts and lists only under its own root, an unbound one under every line', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());
    [$isoQuoteId, $aplQuoteId] = createRootQuoteRequest([$tree['iso9001'], $tree['tirocinio']]);

    $legacy = Opportunity::factory()->create();
    foreach ([$tree['iso9001'], $tree['tirocinio']] as $category) {
        OpportunityProductLine::factory()->create(['opportunity_id' => $legacy->id, 'product_category_id' => $category->id, 'business_function_id' => $category->business_function_id]);
    }
    $unbound = Quote::factory()->for($legacy)->create();

    $counts = collect($this->getJson('/api/request-management/product-categories')->assertOk()->json('data.categories'))
        ->pluck('requests_count', 'id');
    expect($counts[$tree['iso9001']->id])->toBe(2)
        ->and($counts[$tree['tirocinio']->id])->toBe(2);

    $isoRows = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 50, 'productCategoryBranchId' => $tree['iso']->id])
        ->assertOk()->json('items');
    expect(collect($isoRows)->pluck('id')->sort()->values()->all())->toBe(collect([$isoQuoteId, $unbound->id])->sort()->values()->all());

    $aplRows = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 50, 'productCategoryId' => $tree['tirocinio']->id])
        ->assertOk()->json('items');
    expect(collect($aplRows)->pluck('id')->sort()->values()->all())->toBe(collect([$aplQuoteId, $unbound->id])->sort()->values()->all());

    $filtered = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'filterModel' => ['product_categories' => ['filterType' => 'set', 'values' => ['ISO 9001']]],
    ])->assertOk()->json('items');
    expect(collect($filtered)->pluck('id')->sort()->values()->all())->toBe(collect([$isoQuoteId, $unbound->id])->sort()->values()->all());
});

it('AC-005: the work panel of a bound offer shows only its root lines and the root', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());
    [, $aplQuoteId] = createRootQuoteRequest([$tree['iso9001'], $tree['tirocinio']]);

    $panel = $this->getJson("/api/request-management/{$aplQuoteId}")->assertOk();

    expect(collect($panel->json('data.product_lines'))->pluck('product_category.id')->all())->toBe([$tree['tirocinio']->id])
        ->and($panel->json('data.root_product_category'))->toBe(['id' => $tree['apl']->id, 'name' => 'APL']);
});

it('AC-006: a bound offer cannot take a line of another root, and its edit keeps the sibling lines', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());
    [$isoQuoteId] = createRootQuoteRequest([$tree['iso9001'], $tree['tirocinio']]);
    $opportunityId = Quote::findOrFail($isoQuoteId)->opportunity_id;

    $this->patchJson("/api/request-management/{$isoQuoteId}", [
        'product_lines' => [['product_category_id' => $tree['iso9001']->id], ['product_category_id' => $tree['tirocinio']->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['product_lines']);

    $this->patchJson("/api/request-management/{$isoQuoteId}", [
        'product_lines' => [['product_category_id' => $tree['iso14001']->id]],
    ])->assertOk();

    expect(OpportunityProductLine::query()->where('opportunity_id', $opportunityId)->pluck('product_category_id')->sort()->values()->all())
        ->toBe(collect([$tree['iso14001']->id, $tree['tirocinio']->id])->sort()->values()->all());
});

it('AC-007: the opportunity of bound offers cannot add or drop a root, but may change category inside one', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());
    [$isoQuoteId] = createRootQuoteRequest([$tree['iso9001']]);
    $opportunityId = Quote::findOrFail($isoQuoteId)->opportunity_id;

    $this->patchJson("/api/opportunities/{$opportunityId}", [
        'product_lines' => [['product_category_id' => $tree['iso9001']->id], ['product_category_id' => $tree['tirocinio']->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['product_lines']);

    $this->patchJson("/api/opportunities/{$opportunityId}", [
        'product_lines' => [['product_category_id' => $tree['tirocinio']->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['product_lines']);

    $this->patchJson("/api/opportunities/{$opportunityId}", [
        'product_lines' => [['product_category_id' => $tree['iso14001']->id]],
    ])->assertOk();
});

it('AC-008: the workflow classification of a bound offer reads only its root lines', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());
    [$isoQuoteId, $aplQuoteId] = createRootQuoteRequest([$tree['iso9001'], $tree['tirocinio']]);

    $source = app(QuoteClassificationSource::class);
    $load = static fn (int $id): Quote => Quote::with(['offerLines.product.category', 'opportunity.productLines'])->findOrFail($id);

    expect($source->categoryIds($load($isoQuoteId)))->toBe([$tree['iso9001']->id])
        ->and($source->categoryIds($load($aplQuoteId)))->toBe([$tree['tirocinio']->id]);
});

it('AC-009: an offer row with a product of another root is refused on a bound offer', function () {
    $tree = rootQuoteTree();
    Sanctum::actingAs(rootQuoteActor());
    [$isoQuoteId] = createRootQuoteRequest([$tree['iso9001'], $tree['tirocinio']]);

    $this->patchJson("/api/request-management/{$isoQuoteId}", [
        'offer_lines' => [['product_id' => $tree['aplProduct']->id, 'quantity' => 1, 'unit_price' => 50]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['offer_lines']);

    expect(Quote::findOrFail($isoQuoteId)->offerLines()->count())->toBe(0);
});

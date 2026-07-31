<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/tables/{domain}/columns + PATCH .../rows/{row} — "Prodotti di
// interesse" as a grid column with the SAME behaviour as the form picker (user
// directive 2026-07-23): the option list is scoped to the row's own
// product-line categories, the whole catalogue can still be picked from (and a
// cross-category pick is resolved per domain: `opportunities` adds the missing
// product line like its form, `request-management` refuses it — user directive
// 2026-07-31), and the collection is MANDATORY, so it can never be cleared
// in-grid.
//
// Both domains that expose the column are covered here, since they share one
// declaration (App\Tables\Shared\ProductsOfInterestColumn) and must never drift.

uses(RefreshDatabase::class);

if (! function_exists('productsColumnActor')) {
    /**
     * @param  array<int, string>  $abilities  fully-qualified ability names
     */
    function productsColumnActor(array $abilities, bool $canViewProducts = true): User
    {
        foreach ([
            'opportunities.viewAny', 'opportunities.view', 'opportunities.update',
            'request-management.viewAny', 'request-management.view', 'request-management.update',
            'request-management.viewAll', 'products.viewAny',
        ] as $ability) {
            Permission::findOrCreate($ability);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($abilities);

        if ($canViewProducts) {
            $user->givePermissionTo('products.viewAny');
        }

        return $user;
    }
}

if (! function_exists('productsColumnOpportunity')) {
    /** An opportunity carrying ONE product line, with the actor as its GA2 operator (request-management's own row scope). */
    function productsColumnOpportunity(User $operator, ProductCategory $category): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

if (! function_exists('productsColumnCategory')) {
    function productsColumnCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

if (! function_exists('productsColumnConfig')) {
    /** @return Collection<string, array<string, mixed>> */
    function productsColumnConfig(string $domain): Collection
    {
        return collect(test()->getJson("/api/tables/{$domain}/columns")->assertOk()->json('data.columns'))
            ->keyBy('id');
    }
}

// ---------------------------------------------------------------------------
// The config the grid builds the editor from
// ---------------------------------------------------------------------------

it('advertises a multiselect editor over products, scoped by the row categories', function (string $domain) {
    Sanctum::actingAs(productsColumnActor(["{$domain}.viewAny", "{$domain}.update"]));

    $column = productsColumnConfig($domain)['products_of_interest'];

    expect($column['editable'])->toBeTrue()
        ->and($column['editor'])->toBe('multiselect')
        ->and($column['sortable'])->toBeFalse()
        ->and($column['relation']['resource'])->toBe('products')
        ->and($column['relation']['scope'])->toBe(['category_ids' => 'product_category_ids']);
})->with(['opportunities', 'request-management']);

// ADR 0011 amended (2026-07-31): the picker no longer needs `products.viewAny`,
// so neither does the cell — the actor's own `{domain}.update` is the gate.
it('stays editable without products.viewAny', function (string $domain) {
    Sanctum::actingAs(productsColumnActor(["{$domain}.viewAny", "{$domain}.update"], canViewProducts: false));

    expect(productsColumnConfig($domain)['products_of_interest']['editable'])->toBeTrue();
})->with(['opportunities', 'request-management']);

// ---------------------------------------------------------------------------
// The row payload the cell and the editor read
// ---------------------------------------------------------------------------

it('projects the selected products and the scope category ids on every row', function (string $domain) {
    $actor = productsColumnActor(["{$domain}.viewAny", "{$domain}.update", 'request-management.viewAll']);
    $category = productsColumnCategory();
    $opportunity = productsColumnOpportunity($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fibra 1000']);
    $opportunity->productsOfInterest()->sync([$product->id]);
    Sanctum::actingAs($actor);

    $row = collect($this->postJson("/api/tables/{$domain}/rows", [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [], 'filterModel' => [],
    ])->assertOk()->json('items'))->firstWhere('id', $opportunity->id);

    expect($row['products_of_interest'])->toBe([['id' => $product->id, 'name' => 'Fibra 1000']])
        ->and($row['product_category_ids'])->toBe([$category->id]);
})->with(['opportunities', 'request-management']);

// ---------------------------------------------------------------------------
// The write path
// ---------------------------------------------------------------------------

it('PATCH replaces the whole collection and returns the re-mapped row', function (string $domain) {
    $actor = productsColumnActor(["{$domain}.viewAny", "{$domain}.update", 'request-management.viewAll']);
    $category = productsColumnCategory();
    $opportunity = productsColumnOpportunity($actor, $category);
    $dropped = Product::factory()->create(['category_id' => $category->id]);
    $kept = Product::factory()->create(['category_id' => $category->id]);
    $opportunity->productsOfInterest()->sync([$dropped->id]);
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/{$domain}/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [$kept->id],
    ])->assertOk()->json('data');

    expect($opportunity->fresh()->productsOfInterest->pluck('id')->all())->toBe([$kept->id])
        ->and($row['products_of_interest'])->toHaveCount(1);
})->with(['opportunities', 'request-management']);

// The two domains DIVERGE here since the user directive 2026-07-31, so the
// dataset splits: `opportunities` keeps the auto-add rule
// (OpportunityProductLineCoverage), `request-management` refuses the pick
// (RequestProductCategoryCoherence) now that its own commercials edit the
// funzione/categoria rows themselves. Everything else on this column is still
// shared, hence still covered by one declaration.
it('PATCH with a product outside the row categories adds its product line (opportunities: same rule as the form)', function () {
    $actor = productsColumnActor(['opportunities.viewAny', 'opportunities.update']);
    $category = productsColumnCategory();
    $opportunity = productsColumnOpportunity($actor, $category);
    $otherCategory = productsColumnCategory();
    $outsider = Product::factory()->create(['category_id' => $otherCategory->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [$outsider->id],
    ])->assertOk();

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $otherCategory->business_function_id,
        'product_category_id' => $otherCategory->id,
    ]);
    expect($opportunity->fresh()->productLines)->toHaveCount(2);
});

it('PATCH with a product outside the row categories is refused (request-management: coherence rule)', function () {
    $actor = productsColumnActor(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);
    $category = productsColumnCategory();
    $opportunity = productsColumnOpportunity($actor, $category);
    $otherCategory = productsColumnCategory();
    $outsider = Product::factory()->create(['category_id' => $otherCategory->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [$outsider->id],
    ])->assertStatus(422);

    expect($opportunity->fresh()->productLines)->toHaveCount(1)
        ->and($opportunity->fresh()->productsOfInterest)->toHaveCount(0);
});

it('PATCH with an empty collection -> 422, the collection is kept (mandatory field)', function (string $domain) {
    $actor = productsColumnActor(["{$domain}.viewAny", "{$domain}.update", 'request-management.viewAll']);
    $category = productsColumnCategory();
    $opportunity = productsColumnOpportunity($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $opportunity->productsOfInterest()->sync([$product->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/{$domain}/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [],
    ])->assertStatus(422);

    expect($opportunity->fresh()->productsOfInterest)->toHaveCount(1);
})->with(['opportunities', 'request-management']);

it('PATCH with an unknown product id -> 422, nothing written', function (string $domain) {
    $actor = productsColumnActor(["{$domain}.viewAny", "{$domain}.update", 'request-management.viewAll']);
    $category = productsColumnCategory();
    $opportunity = productsColumnOpportunity($actor, $category);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/{$domain}/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [999999],
    ])->assertStatus(422);

    expect($opportunity->fresh()->productsOfInterest)->toHaveCount(0);
})->with(['opportunities', 'request-management']);

// The write follows the config: `products.viewAny` gates neither the picker nor
// the value-scope guard any more (ADR 0011 amended 2026-07-31).
it('PATCH without products.viewAny -> 200, the collection is written', function (string $domain) {
    $actor = productsColumnActor(["{$domain}.viewAny", "{$domain}.update", 'request-management.viewAll'], canViewProducts: false);
    $category = productsColumnCategory();
    $opportunity = productsColumnOpportunity($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/{$domain}/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [$product->id],
    ])->assertOk();

    expect($opportunity->fresh()->productsOfInterest->pluck('id')->all())->toBe([$product->id]);
})->with(['opportunities', 'request-management']);

// ---------------------------------------------------------------------------
// Filtering (set filter + distinct values), shared by both domains
// ---------------------------------------------------------------------------

it('filters the rows by product name and enumerates its distinct values', function (string $domain) {
    $actor = productsColumnActor(["{$domain}.viewAny", "{$domain}.update", 'request-management.viewAll']);
    $category = productsColumnCategory();
    $matching = productsColumnOpportunity($actor, $category);
    $other = productsColumnOpportunity($actor, $category);
    $wanted = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fibra 1000']);
    $unwanted = Product::factory()->create(['category_id' => $category->id, 'name' => 'ADSL 20']);
    $matching->productsOfInterest()->sync([$wanted->id]);
    $other->productsOfInterest()->sync([$unwanted->id]);
    Sanctum::actingAs($actor);

    $items = $this->postJson("/api/tables/{$domain}/rows", [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [],
        'filterModel' => ['products_of_interest' => ['filterType' => 'set', 'values' => ['Fibra 1000']]],
    ])->assertOk()->json('items');

    expect(collect($items)->pluck('id')->all())->toBe([$matching->id]);

    $values = $this->postJson("/api/tables/{$domain}/values", [
        'columnId' => 'products_of_interest',
    ])->assertOk()->json('data.values');

    expect($values)->toBe(['ADSL 20', 'Fibra 1000']);
})->with(['opportunities', 'request-management']);

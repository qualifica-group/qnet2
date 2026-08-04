<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * The three read channels the "linea di prodotto" + "prodotti di interesse"
 * block of the request-management create form feeds on, exercised as the
 * Commercial role — the one role whose grants stop at its own module. Split
 * out of TestUsersSeederTest (file-size hard limit, engineering.md §6).
 */
const COMMERCIAL_EMAIL = 'campania@commerciale.com';

function commercialActor(): User
{
    return User::query()->where('email', COMMERCIAL_EMAIL)->firstOrFail();
}

// The two halves of a product-line row read DIFFERENT channels: the "funzione
// aziendale" a for-select (ungated, ADR 0011 amended 2026-07-31), the
// "categoria prodotto" the structural tree (gated by
// ProductCategoryPolicy::viewAny since the user directive 2026-08-03). Without
// `product-categories.viewAny` the first select answered and the second stayed
// empty.
it('lets the commercial role read both channels the product-lines row selects feed on', function () {
    $this->seed(TestUsersSeeder::class);

    Sanctum::actingAs(commercialActor());

    $this->getJson('/api/business-functions/for-select')->assertOk();
    $this->getJson('/api/product-categories/tree')->assertOk();

    // Read-only: the grant is `viewAny` alone, so writing a category stays 403.
    // The menu entry, gated on `product-categories.view`, is covered by the
    // exact-routes assertion in TestUsersSeederTest.
    $this->postJson('/api/product-categories', ['name' => 'Nuova categoria'])->assertForbidden();
});

// The "prodotti di interesse" picker downstream of those rows reads a THIRD
// channel, `products/for-select`, ungated (ADR 0011 amended) even though the
// role holds no `products.*` at all. Its emptiness is SCOPE, not permission:
// `ProductsOfInterestField` disables itself while no category is chosen, and
// the endpoint filters on the EXACT `category_id` of the picked categories.
it('lets the commercial role read the products picker scoped to the categories of its rows', function () {
    $this->seed(TestUsersSeeder::class);

    $category = ProductCategory::factory()->create();
    $inScope = Product::factory()->create(['category_id' => $category->id]);
    Product::factory()->create(['category_id' => ProductCategory::factory()->create()->id]);

    $actor = commercialActor();
    expect($actor->can('products.viewAny'))->toBeFalse();

    Sanctum::actingAs($actor);

    $this->getJson('/api/products/for-select?category_ids[]='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $inScope->id);

    // A category holding nothing answers 200 with an empty page: what the
    // operator reads as "nessun prodotto" is the catalogue, not a 403.
    $this->getJson('/api/products/for-select?category_ids[]='.ProductCategory::factory()->create()->id)
        ->assertOk()
        ->assertJsonCount(0, 'items')
        ->assertJsonPath('pagination.total', 0);
});

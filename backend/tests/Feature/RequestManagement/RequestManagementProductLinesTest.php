<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * "Funzione aziendale" + "categoria prodotto" from the operative work panel
 * (user directive 2026-07-31): the `product_lines` collection the create form
 * already writes is now editable on an existing request too — PATCH
 * /api/request-management/{quote} (spec 0086, D-2) replaces it under the SAME
 * rules as the opportunities form (ValidatesProductLines).
 *
 * Plus THE COHERENCE RULE, on both write channels: a product of interest must
 * belong to one of the request's product categories, so neither dropping the
 * category nor picking an outside product is accepted silently
 * (RequestProductCategoryCoherence).
 */
uses(RefreshDatabase::class);

if (! function_exists('productLineActor')) {
    function productLineActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('request-management.update');

        return $user;
    }
}

if (! function_exists('productLineRequest')) {
    /** A quote the actor supervises, carrying one product line. */
    function productLineRequest(User $operator, ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

if (! function_exists('productLineCategory')) {
    /** A category carrying its own business function (no inheritance involved). */
    function productLineCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

it('PATCH product_lines replaces the collection and exposes it back', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $quote = productLineRequest($actor, $category);
    $replacement = productLineCategory();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
    ])->assertOk();

    $response->assertJsonPath('data.product_lines.0.business_function.id', $replacement->business_function_id)
        ->assertJsonPath('data.product_lines.0.product_category.id', $replacement->id)
        ->assertJsonCount(1, 'data.product_lines');
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $replacement->id,
    ]);
    $this->assertDatabaseMissing('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
});

it('PATCH product_lines rejects an empty collection and a category outside the paired business function', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $quote = productLineRequest($actor, $category);
    $other = productLineCategory();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['product_lines' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('product_lines');

    // The category belongs to its OWN business function, not to this one.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $other->id,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.business_function_id');

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
});

it('PATCH product_lines refuses to drop a category whose products of interest are still selected', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $quote = productLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fibra 1000']);
    $quote->opportunity->productsOfInterest()->sync([$product->id]);
    $replacement = productLineCategory();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
});

// REQUIREMENT CHANGED (spec 0086, AC-022): `products_of_interest` is no
// longer accepted by PATCH /api/request-management/{quote} at all (the
// grid's replacement column, `offer_lines`, is read-only). The former
// "same save moves the products of interest along" scenario is therefore
// impossible on this channel now: a submitted `products_of_interest` key
// is silently ignored, so dropping the category still 422s against
// whatever is PERSISTED on the Opportunity — this test now proves that
// ignoring, instead of the write it used to prove.
it('PATCH silently ignores a submitted products_of_interest key (AC-022): the coherence check still runs against the PERSISTED set', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $quote = productLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote->opportunity->productsOfInterest()->sync([$product->id]);
    $replacement = productLineCategory();
    $replacementProduct = Product::factory()->create(['category_id' => $replacement->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
        'products_of_interest' => [$replacementProduct->id],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
    $this->assertDatabaseMissing('opportunity_product', [
        'opportunity_id' => $quote->opportunity_id,
        'product_id' => $replacementProduct->id,
    ]);
});

it('logs the product-lines change explicitly (the collection is a relation, never in the fillable diff)', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $quote = productLineRequest($actor, $category);
    $replacement = productLineCategory();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
    ])->assertOk();

    $opportunity = $quote->opportunity;
    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['attributes']['product_lines'])->toBe([[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]])
        ->and($activity->properties['old']['product_lines'])->toBe([[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]]);
});

// ---------------------------------------------------------------------------
// Creation channel (user directive 2026-07-31): the picker is available at
// creation too, and the SAME coherence rule applies — both collections travel
// in the payload, so StoreRequestRequest checks them against each other.
// ---------------------------------------------------------------------------

it('POST accepts products of interest belonging to the submitted product categories', function () {
    $actor = productLineActor();
    $actor->givePermissionTo('request-management.create');
    $category = productLineCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'products_of_interest' => [$product->id],
    ])->assertCreated();

    // Spec 0086, D-7: the response's own `offer_lines` projects the OFFER's
    // (Quote) REVENUE lines, not the Opportunity's products of interest — the
    // freshly-created offer has none yet (AC-028), so `offer_lines` stays
    // empty even though the product of interest below WAS written.
    $response->assertJsonPath('data.offer_lines', []);
    $this->assertDatabaseHas('opportunity_product', [
        'opportunity_id' => Opportunity::query()->latest('id')->value('id'),
        'product_id' => $product->id,
    ]);
});

it('POST refuses a product of interest outside the submitted product categories, creating nothing', function () {
    $actor = productLineActor();
    $actor->givePermissionTo('request-management.create');
    $category = productLineCategory();
    $outsideProduct = Product::factory()->create(['category_id' => productLineCategory()->id]);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'products_of_interest' => [$outsideProduct->id],
    ])->assertStatus(422)->assertJsonValidationErrors('products_of_interest');

    $this->assertDatabaseCount('opportunities', 0);
});

it('POST stays valid without products of interest: the check only runs when there are any', function () {
    $actor = productLineActor();
    $actor->givePermissionTo('request-management.create');
    $category = productLineCategory();
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
    ])->assertCreated();

    expect($response->json('data.offer_lines'))->toBe([]);
});

it('exposes product_lines as an editable field in the panel permissions', function () {
    $actor = productLineActor();
    $actor->givePermissionTo('request-management.view');
    $category = productLineCategory();
    $quote = productLineRequest($actor, $category);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.product_lines.visible', true)
        ->assertJsonPath('permissions.fields.product_lines.editable', true);
});

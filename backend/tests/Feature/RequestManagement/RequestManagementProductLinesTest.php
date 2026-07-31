<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * "Funzione aziendale" + "categoria prodotto" from the operative work panel
 * (user directive 2026-07-31): the `product_lines` collection the create form
 * already writes is now editable on an existing request too — PATCH
 * /api/request-management/{opportunity} replaces it under the SAME rules as
 * the opportunities form (ValidatesProductLines), and refuses to drop a
 * category whose products of interest would be left uncovered.
 */
uses(RefreshDatabase::class);

if (! function_exists('productLineActor')) {
    function productLineActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('request-management.update');

        return $user;
    }
}

if (! function_exists('productLineRequest')) {
    /** A request the actor owns as GA2 "Operatore", carrying one product line. */
    function productLineRequest(User $manager, ProductCategory $category): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
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
    $opportunity = productLineRequest($actor, $category);
    $replacement = productLineCategory();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/request-management/{$opportunity->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
    ])->assertOk();

    $response->assertJsonPath('data.product_lines.0.business_function.id', $replacement->business_function_id)
        ->assertJsonPath('data.product_lines.0.product_category.id', $replacement->id)
        ->assertJsonCount(1, 'data.product_lines');
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $replacement->id,
    ]);
    $this->assertDatabaseMissing('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $category->id,
    ]);
});

it('PATCH product_lines rejects an empty collection and a category outside the paired business function', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $opportunity = productLineRequest($actor, $category);
    $other = productLineCategory();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['product_lines' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('product_lines');

    // The category belongs to its OWN business function, not to this one.
    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $other->id,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.business_function_id');

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $category->id,
    ]);
});

it('PATCH product_lines refuses to drop a category whose products of interest are still selected', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $opportunity = productLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fibra 1000']);
    $opportunity->productsOfInterest()->sync([$product->id]);
    $replacement = productLineCategory();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $category->id,
    ]);
});

it('PATCH product_lines accepts the same save that moves the products of interest along', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $opportunity = productLineRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $opportunity->productsOfInterest()->sync([$product->id]);
    $replacement = productLineCategory();
    $replacementProduct = Product::factory()->create(['category_id' => $replacement->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
        'products_of_interest' => [$replacementProduct->id],
    ])->assertOk()->assertJsonCount(1, 'data.product_lines');

    $this->assertDatabaseMissing('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $category->id,
    ]);
    $this->assertDatabaseHas('opportunity_product', [
        'opportunity_id' => $opportunity->id,
        'product_id' => $replacementProduct->id,
    ]);
});

it('logs the product-lines change explicitly (the collection is a relation, never in the fillable diff)', function () {
    $actor = productLineActor();
    $category = productLineCategory();
    $opportunity = productLineRequest($actor, $category);
    $replacement = productLineCategory();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
    ])->assertOk();

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

it('exposes product_lines as an editable field in the panel permissions', function () {
    $actor = productLineActor();
    $actor->givePermissionTo('request-management.view');
    $category = productLineCategory();
    $opportunity = productLineRequest($actor, $category);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.product_lines.visible', true)
        ->assertJsonPath('permissions.fields.product_lines.editable', true);
});

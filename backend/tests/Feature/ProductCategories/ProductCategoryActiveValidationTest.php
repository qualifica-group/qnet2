<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `is_active` (spec 0208) on the write side: a category (or a product) whose
 * category is EFFECTIVELY inactive (own flag false, or a descendant of an
 * inactive one) cannot be linked to a NEW record; the value already
 * persisted on the record being updated stays valid (D-2).
 */
uses(RefreshDatabase::class);

if (! function_exists('activeValidationActor')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. `products.create`
     */
    function activeValidationActor(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

/** A category that is inactive only because its parent is (own flag true). */
function activeValidationInactiveChild(?BusinessFunction $function = null): ProductCategory
{
    $parent = ProductCategory::factory()->create(['is_active' => false, 'business_function_id' => $function?->id]);

    return ProductCategory::factory()->childOf($parent)->create();
}

// ---------------------------------------------------------------------------
// products — AC-009 / AC-015
// ---------------------------------------------------------------------------

it('AC-009: POST product rejects an inactive category (own flag or inherited) with the activity message only', function (bool $inherited) {
    $category = $inherited ? activeValidationInactiveChild() : ProductCategory::factory()->create(['is_active' => false]);
    Sanctum::actingAs(activeValidationActor(['products.create']));

    $response = $this->postJson('/api/products', [
        'name' => 'Widget', 'cost' => 10, 'price' => 20, 'product_type' => 'SERVICE',
        'category_id' => $category->id,
    ])->assertStatus(422)->assertJsonValidationErrors('category_id');

    expect($response->json('errors.category_id'))->toBe([__('This product category is not active.')])
        ->and(Product::count())->toBe(0);
})->with([[false], [true]]);

it('AC-009: PATCH product resubmitting its own category after it was deactivated passes, another inactive one is rejected', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $category->update(['is_active' => false]);
    Sanctum::actingAs(activeValidationActor(['products.update']));

    $this->patchJson("/api/products/{$product->id}", ['name' => 'Renamed', 'category_id' => $category->id])->assertOk();

    $this->patchJson("/api/products/{$product->id}", ['category_id' => ProductCategory::factory()->create(['is_active' => false])->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

it('AC-015: an inactive AND unselectable category yields ONE message; an active unselectable one keeps the 0074 message', function () {
    Sanctum::actingAs(activeValidationActor(['products.create']));
    $payload = ['name' => 'Widget', 'cost' => 10, 'price' => 20, 'product_type' => 'SERVICE'];

    $both = ProductCategory::factory()->create(['is_active' => false, 'is_selectable' => false]);
    $this->postJson('/api/products', [...$payload, 'category_id' => $both->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.category_id', [__('This product category is not active.')]);

    $containerOnly = ProductCategory::factory()->create(['is_selectable' => false]);
    $this->postJson('/api/products', [...$payload, 'category_id' => $containerOnly->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.category_id', [__('This product category is not selectable.')]);

    $this->postJson('/api/products', [...$payload, 'category_id' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('errors.category_id', [__('The selected product category is invalid.')]);
});

// ---------------------------------------------------------------------------
// opportunities — AC-010 / AC-014
// ---------------------------------------------------------------------------

/** @return array<string, mixed> */
function activeValidationOpportunityPayload(array $lines, array $products = []): array
{
    return [
        'name' => 'Opportunity',
        'registry_id' => Registry::factory()->create()->id,
        'supervisor_id' => User::factory()->create()->id,
        'product_lines' => $lines,
        'products_of_interest' => $products,
    ];
}

it('AC-010: opportunity POST rejects a product line on an inactive category', function () {
    $function = BusinessFunction::factory()->create();
    $inactive = ProductCategory::factory()->create(['business_function_id' => $function->id, 'is_active' => false]);
    Sanctum::actingAs(activeValidationActor(['opportunities.create']));

    $this->postJson('/api/opportunities', activeValidationOpportunityPayload(
        [['business_function_id' => $function->id, 'product_category_id' => $inactive->id]],
    ))->assertStatus(422)->assertJsonValidationErrors('product_lines.0.product_category_id');

    expect(Opportunity::count())->toBe(0);
});

it('AC-010/AC-014: opportunity PATCH resubmitting unchanged lines and products passes after the category is deactivated, a NEW inactive product does not', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs(activeValidationActor(['opportunities.create', 'opportunities.update']));
    $lines = [['business_function_id' => $function->id, 'product_category_id' => $category->id]];

    $id = $this->postJson('/api/opportunities', activeValidationOpportunityPayload($lines, [$product->id]))
        ->assertCreated()->json('data.id');

    $category->update(['is_active' => false]);

    $this->patchJson("/api/opportunities/{$id}", ['product_lines' => $lines, 'products_of_interest' => [$product->id]])->assertOk();

    $newInactive = Product::factory()->create(['category_id' => ProductCategory::factory()->create(['is_active' => false])->id]);
    $this->patchJson("/api/opportunities/{$id}", ['products_of_interest' => [$product->id, $newInactive->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('products_of_interest.1');
});

it('AC-014: opportunity POST rejects a product of an inactive category in products_of_interest', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $inactiveProduct = Product::factory()->create(['category_id' => activeValidationInactiveChild()->id]);
    Sanctum::actingAs(activeValidationActor(['opportunities.create']));

    $this->postJson('/api/opportunities', activeValidationOpportunityPayload(
        [['business_function_id' => $function->id, 'product_category_id' => $category->id]],
        [$inactiveProduct->id],
    ))->assertStatus(422)->assertJsonValidationErrors('products_of_interest.0');
});

// ---------------------------------------------------------------------------
// leads — AC-014
// ---------------------------------------------------------------------------

it('AC-014: lead POST rejects a product of an inactive category; PATCH keeps the linked one', function () {
    $actor = activeValidationActor(['leads.create', 'leads.update', 'leads.view']);
    $campaign = Campaign::factory()->create();
    $category = $campaign->productLines()->first()->productCategory;
    $product = Product::factory()->create(['category_id' => $category->id]);
    $base = ['registry_id' => Registry::factory()->create()->id, 'campaign_id' => $campaign->id, 'source_id' => Source::factory()->create()->id];
    Sanctum::actingAs($actor);

    $leadId = $this->postJson('/api/leads', [...$base, 'products_of_interest' => [$product->id]])->assertCreated()->json('data.id');
    $category->update(['is_active' => false]);

    $this->postJson('/api/leads', [...$base, 'products_of_interest' => [$product->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('products_of_interest.0');
    expect(Lead::count())->toBe(1);

    $this->patchJson("/api/leads/{$leadId}", ['products_of_interest' => [$product->id]])->assertOk();
});

// ---------------------------------------------------------------------------
// commission configurations — AC-011
// ---------------------------------------------------------------------------

/** @return array<string, mixed> */
function activeValidationCommissionPayload(array $overrides): array
{
    return [
        'name' => 'Rule', 'recipient_role' => 'COMMERCIAL', 'commission_type' => 'PERCENTAGE',
        'value' => 5, 'priority' => 0, 'valid_from' => '2026-01-01', 'status' => 'ACTIVE',
        ...$overrides,
    ];
}

it('AC-011: commission configuration rejects an inactive category and a product of an inactive category', function () {
    Sanctum::actingAs(activeValidationActor(['commission-configurations.create']));

    $this->postJson('/api/commission-configurations', activeValidationCommissionPayload([
        'application_scope' => 'PRODUCT_CATEGORY',
        'product_category_id' => ProductCategory::factory()->create(['is_active' => false])->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('product_category_id');

    $product = Product::factory()->create(['category_id' => activeValidationInactiveChild()->id]);
    $response = $this->postJson('/api/commission-configurations', activeValidationCommissionPayload([
        'application_scope' => 'PRODUCT',
        'product_id' => $product->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('product_id');

    expect($response->json('errors.product_id'))->toBe([__('This product belongs to an inactive category.')]);
});

it('AC-011: commission configuration resubmitting its saved category or product passes after deactivation', function () {
    Sanctum::actingAs(activeValidationActor(['commission-configurations.create', 'commission-configurations.update']));
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create();

    $byCategory = $this->postJson('/api/commission-configurations', activeValidationCommissionPayload([
        'application_scope' => 'PRODUCT_CATEGORY', 'product_category_id' => $category->id,
    ]))->assertCreated()->json('data.id');
    $byProduct = $this->postJson('/api/commission-configurations', activeValidationCommissionPayload([
        'application_scope' => 'PRODUCT', 'product_id' => $product->id,
    ]))->assertCreated()->json('data.id');

    $category->update(['is_active' => false]);
    $product->category()->update(['is_active' => false]);

    $this->patchJson("/api/commission-configurations/{$byCategory}", [
        'application_scope' => 'PRODUCT_CATEGORY', 'product_category_id' => $category->id, 'priority' => 3,
    ])->assertOk();
    $this->patchJson("/api/commission-configurations/{$byProduct}", [
        'application_scope' => 'PRODUCT', 'product_id' => $product->id, 'priority' => 3,
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// user competence — AC-012
// ---------------------------------------------------------------------------

it('AC-012: competence row on an inactive category is rejected; the saved one passes; an active container stays admitted', function () {
    Sanctum::actingAs(activeValidationActor(['users.update']));
    $function = BusinessFunction::factory()->create();
    $saved = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $target = User::factory()->create();
    EmploymentProfile::factory()->for($target)->competentIn($function, $saved)->create();
    $row = fn (ProductCategory $category): array => ['business_function_id' => $function->id, 'product_category_id' => $category->id];

    $saved->update(['is_active' => false]);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [$row($saved)]]])->assertOk();

    $inactiveChild = ProductCategory::factory()->childOf($saved)->create();
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [$row($saved), $row($inactiveChild)]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employment.product_lines.1.product_category_id' => __('This product category is not active.')]);

    $container = ProductCategory::factory()->create(['business_function_id' => $function->id, 'is_selectable' => false]);
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [$row($container)]]])->assertOk();
});

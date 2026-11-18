<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0410: the root-owned `request_registry_type` setting of a product
 * category (AC-001/AC-002) and the type the Gestione Richieste create form
 * receives for the picked categories (AC-003).
 */
uses(RefreshDatabase::class);

if (! function_exists('requestRegistryTypeCreator')) {
    function requestRegistryTypeCreator(): User
    {
        Permission::findOrCreate('request-management.create');
        $user = User::factory()->create();
        $user->givePermissionTo('request-management.create');

        return $user;
    }
}

it('AC-001: a fresh root starts on individual; a root may pick company', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Formazione privati'])
        ->assertCreated()
        ->assertJsonPath('data.request_registry_type', 'individual');

    $this->postJson('/api/product-categories', ['name' => 'Consulenza', 'request_registry_type' => 'company'])
        ->assertCreated()
        ->assertJsonPath('data.request_registry_type', 'company');
});

it('AC-002: a child inherits the type of its root and shows where it comes from', function () {
    $root = ProductCategory::factory()->create(['name' => 'Consulenza', 'request_registry_type' => 'company']);
    Sanctum::actingAs(productCategoryUserWith(['create', 'view']));

    $childId = $this->postJson('/api/product-categories', ['name' => 'Finanza agevolata', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.request_registry_type', 'company')
        ->json('data.id');

    $this->getJson("/api/product-categories/{$childId}")
        ->assertOk()
        ->assertJsonPath('data.request_registry_type_source_category.id', $root->id);
});

it('AC-002: changing the root type realigns the whole branch', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    $grandchild = ProductCategory::factory()->childOf($child)->create();
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['request_registry_type' => 'company'])
        ->assertOk()
        ->assertJsonPath('data.request_registry_type', 'company');

    expect($child->fresh()->request_registry_type->value)->toBe('company')
        ->and($grandchild->fresh()->request_registry_type->value)->toBe('company');
});

it('AC-002: moving a category under another root takes that root type', function () {
    $individual = ProductCategory::factory()->create(['request_registry_type' => 'individual']);
    $company = ProductCategory::factory()->create(['request_registry_type' => 'company']);
    $child = ProductCategory::factory()->childOf($individual)->create(['request_registry_type' => 'individual']);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['parent_id' => $company->id])->assertOk();

    expect($child->fresh()->request_registry_type->value)->toBe('company');
});

it('AC-002: a child may not define a type different from its root', function () {
    $root = ProductCategory::factory()->create(['request_registry_type' => 'company']);
    $child = ProductCategory::factory()->childOf($root)->create(['request_registry_type' => 'company']);
    Sanctum::actingAs(productCategoryUserWith(['create', 'update']));

    $this->postJson('/api/product-categories', ['name' => 'X', 'parent_id' => $root->id, 'request_registry_type' => 'individual'])
        ->assertStatus(422);
    $this->patchJson("/api/product-categories/{$child->id}", ['request_registry_type' => 'individual'])->assertStatus(422);
    $this->patchJson("/api/product-categories/{$child->id}", ['request_registry_type' => 'company'])->assertOk();
});

it('rejects an unknown type', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Bad', 'request_registry_type' => 'robot'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('request_registry_type');
});

it('the tree carries the type of every node', function () {
    $root = ProductCategory::factory()->create(['request_registry_type' => 'company']);
    ProductCategory::factory()->childOf($root)->create(['request_registry_type' => 'company']);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $this->getJson('/api/product-categories/tree')
        ->assertOk()
        ->assertJsonPath('data.0.request_registry_type', 'company')
        ->assertJsonPath('data.0.children.0.request_registry_type', 'company');
});

it('AC-003: form-context returns the type of the first product line category', function () {
    $functionId = BusinessFunction::factory()->create()->id;
    $company = ProductCategory::factory()->create(['request_registry_type' => 'company', 'business_function_id' => $functionId]);
    $individual = ProductCategory::factory()->create(['request_registry_type' => 'individual', 'business_function_id' => $functionId]);
    Sanctum::actingAs(requestRegistryTypeCreator());

    $this->postJson('/api/request-management/form-context', ['product_lines' => [
        ['business_function_id' => $functionId, 'product_category_id' => $company->id],
        ['business_function_id' => $functionId, 'product_category_id' => $individual->id],
    ]])->assertOk()->assertJsonPath('data.registry_type', 'company');

    $this->postJson('/api/request-management/form-context', ['product_lines' => [
        ['business_function_id' => $functionId, 'product_category_id' => $individual->id],
        ['business_function_id' => $functionId, 'product_category_id' => $company->id],
    ]])->assertOk()->assertJsonPath('data.registry_type', 'individual');
});

it('AC-003: form-context returns no type without a complete product line', function () {
    Sanctum::actingAs(requestRegistryTypeCreator());

    $this->postJson('/api/request-management/form-context', [
        'product_lines' => [['business_function_id' => null, 'product_category_id' => null]],
    ])->assertOk()->assertJsonPath('data.registry_type', null);
});

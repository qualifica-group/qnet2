<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/{domain}/rows/{row} — mandatory-field enforcement (user
// directive): a field resolved as `required` (App\Authorization\FieldPermission)
// rejects null/blank on inline edit even though it stays editable — derived
// from the SAME field-permission metadata both channels already share, not a
// per-column declaration, so it applies to every mandatory field, present or
// future.

uses(RefreshDatabase::class);

if (! function_exists('mandatoryFieldActor')) {
    function mandatoryFieldActor(): User
    {
        foreach (['viewAny', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }
        Permission::findOrCreate('products.viewAny');

        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.viewAny', 'opportunities.update', 'products.viewAny']);

        return $user;
    }
}

if (! function_exists('requiredProductsOfInterestActor')) {
    /**
     * An actor whose DB field-permission matrix marks `products_of_interest`
     * required: the field is optional in the ceiling (user directive
     * 2026-09-17), so the matrix is what makes it mandatory here.
     */
    function requiredProductsOfInterestActor(): User
    {
        $actor = mandatoryFieldActor();
        $role = Role::create(['name' => 'products-of-interest-required-'.uniqid()]);
        $role->fieldPermissions()->create([
            'resource' => 'opportunities', 'field' => 'products_of_interest', 'visible' => true, 'editable' => true, 'required' => true,
        ]);
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// A required MULTISELECT: "no value" is an empty collection, not null.
// Requirement CHANGED (user directive 2026-09-17): `products_of_interest` is no
// longer mandatory in the ceiling, so the DB matrix marks it required here —
// the empty-collection guard itself is unchanged.
// ---------------------------------------------------------------------------

it('a mandatory field rejects an empty collection -> 422, no write', function () {
    $actor = requiredProductsOfInterestActor();
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $opportunity->productsOfInterest()->sync([$product->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [],
    ])->assertStatus(422);

    expect($opportunity->fresh()->productsOfInterest)->toHaveCount(1);
});

it('a mandatory field accepts a genuine value -> 200, persisted', function () {
    $actor = requiredProductsOfInterestActor();
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'products_of_interest',
        'value' => [$product->id],
    ])->assertOk();

    expect($opportunity->fresh()->productsOfInterest->pluck('id')->all())->toBe([$product->id]);
});

// ---------------------------------------------------------------------------
// Conflict: the CATALOG declares `estimated_value` nullable, but the actor's
// DB field-permission matrix marks it `required` — the more restrictive of
// the two must win (`required` overrides the column's own `nullable`).
// ---------------------------------------------------------------------------

it('a DB-matrix `required:true` on an otherwise-nullable column rejects null (most restrictive wins)', function () {
    Permission::findOrCreate('opportunities.viewAny');
    Permission::findOrCreate('opportunities.update');

    $role = Role::create(['name' => 'estimated-value-required-'.uniqid()]);
    $role->givePermissionTo(['opportunities.viewAny', 'opportunities.update']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities', 'field' => 'estimated_value', 'visible' => true, 'editable' => true, 'required' => true,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $opportunity = Opportunity::factory()->create(['estimated_value' => 500]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => null,
    ])->assertStatus(422);

    expect((float) $opportunity->fresh()->estimated_value)->toBe(500.0);
});

it('without the DB-matrix override, the same nullable column still accepts null (regression)', function () {
    $actor = mandatoryFieldActor();
    $opportunity = Opportunity::factory()->create(['estimated_value' => 500]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => null,
    ])->assertOk();

    expect($opportunity->fresh()->estimated_value)->toBeNull();
});

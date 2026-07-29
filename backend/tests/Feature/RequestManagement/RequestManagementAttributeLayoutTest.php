<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0062: the work panel's additive `attribute_layout` (context=opportunity,
// form_mode=edit for show/update, form_mode=create for store) — AC-007
// regression (applicable_attributes/attribute_values untouched) plus the new
// field's own resolution.

uses(RefreshDatabase::class);

if (! function_exists('requestManagementLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

it('AC-007 regression: with no attribute_layouts row, attribute_layout is null and applicable_attributes/attribute_values are unaffected', function () {
    $actor = requestManagementLayoutUserWith(['view', 'viewAll']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    $opportunity = Opportunity::factory()->create(['attribute_values' => ['material' => 'steel']]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/request-management/{$opportunity->id}")->assertOk();

    expect($response->json('data.attribute_layout'))->toBeNull();
    expect($response->json('data.attribute_values'))->toBe(['material' => 'steel']);
    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['material']);
});

it('GET resolves the merged (opportunity, edit) layout of the contributing category', function () {
    $actor = requestManagementLayoutUserWith(['view', 'viewAll']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'opportunity', 'form_mode' => 'edit']);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/request-management/{$opportunity->id}")->assertOk();

    expect($response->json('data.attribute_layout.sections.0.rows.0.items.0.attribute_code'))->toBe('material');
});

it('PUT (update) returns the SAME attribute_layout shape as GET, post-save', function () {
    $actor = requestManagementLayoutUserWith(['view', 'update', 'viewAll']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'opportunity', 'form_mode' => 'edit']);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->putJson("/api/request-management/{$opportunity->id}", [])->assertOk();

    expect($response->json('data.attribute_layout.sections.0.rows.0.items.0.attribute_code'))->toBe('material');
});

it('POST (create) resolves form_mode=create — a layout configured only for edit does NOT leak into the create response', function () {
    $actor = requestManagementLayoutUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'opportunity', 'form_mode' => 'edit']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
        'source_id' => Source::factory()->create()->id,
    ])->assertCreated();

    expect($response->json('data.attribute_layout'))->toBeNull();
});

it('POST (create) resolves a layout configured for form_mode=create', function () {
    $actor = requestManagementLayoutUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'opportunity', 'form_mode' => 'create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
        'source_id' => Source::factory()->create()->id,
    ])->assertCreated();

    expect($response->json('data.attribute_layout.sections.0.rows.0.items.0.attribute_code'))->toBe('material');
});

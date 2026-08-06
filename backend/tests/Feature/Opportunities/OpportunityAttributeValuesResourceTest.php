<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0049, D-8/AC-050: OpportunityResource is enriched ADDITIVELY with
// `attribute_values` (raw stored map) and `applicable_attributes` (the
// union/dedup-by-code set of the opportunity's product lines' effective
// category attributes), with zero regression on the existing keys.

uses(RefreshDatabase::class);

if (! function_exists('opportunityViewerUser')) {
    function opportunityViewerUser(): User
    {
        Permission::findOrCreate('opportunities.view');
        $user = User::factory()->create();
        $user->givePermissionTo('opportunities.view');

        return $user;
    }
}

it('GET opportunity with product lines and stored values -> attribute_values + applicable_attributes match the categories (AC-050)', function () {
    $actor = opportunityViewerUser();
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $attribute = Attribute::factory()->create(['code' => 'contract_length', 'name' => 'Contract length', 'type' => 'integer']);
    $category->attributes()->attach($attribute->id, ['is_required' => true, 'sort_order' => 0]);

    $opportunity = Opportunity::factory()->create(['attribute_values' => ['contract_length' => 12]]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $category->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk();

    $response->assertJsonPath('data.attribute_values', ['contract_length' => 12]);
    $response->assertJsonPath('data.applicable_attributes.0.code', 'contract_length');
    $response->assertJsonPath('data.applicable_attributes.0.type', 'integer');
    $response->assertJsonPath('data.applicable_attributes.0.is_required', true);

    // No regression on the existing keys/shape. Spec 0083, D-2: the
    // Opportunity carries no working-state field of its own any more —
    // `workflow_status` is gone, `status` is the COMPUTED summary instead.
    $response->assertJsonPath('data.id', $opportunity->id);
    $response->assertJsonPath('data.name', $opportunity->name);
    $response->assertJsonPath('data.registry', ['id' => $opportunity->registry_id, 'name' => $opportunity->registry->name]);
    expect($response->json('data'))->not->toHaveKey('workflow_status')
        ->and($response->json('data'))->toHaveKey('status');
    expect($response->json('data.product_lines'))->toHaveCount(1);
});

it('GET opportunity with no stored values -> attribute_values is an empty object', function () {
    $actor = opportunityViewerUser();
    $opportunity = Opportunity::factory()->create(['attribute_values' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk();

    expect($response->json('data.attribute_values'))->toBe([]);
    $response->assertJson(['data' => ['attribute_values' => []]]);
});

it('GET opportunity with no product lines -> applicable_attributes is an empty array', function () {
    $actor = opportunityViewerUser();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk();

    expect($response->json('data.applicable_attributes'))->toBe([]);
});

it('GET opportunity: two product lines sharing an attribute code dedup into one applicable attribute', function () {
    $actor = opportunityViewerUser();
    $businessFunction = BusinessFunction::factory()->create();
    $categoryOne = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $categoryTwo = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $attribute = Attribute::factory()->create(['code' => 'shared_code']);
    $categoryOne->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0]);
    $categoryTwo->attributes()->attach($attribute->id, ['is_required' => true, 'sort_order' => 0]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $categoryOne->id,
    ]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $categoryTwo->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk();

    $applicable = $response->json('data.applicable_attributes');
    expect($applicable)->toHaveCount(1);
    expect($applicable[0]['code'])->toBe('shared_code');
    // strictest requirement wins across the merged categories.
    expect($applicable[0]['is_required'])->toBeTrue();
});

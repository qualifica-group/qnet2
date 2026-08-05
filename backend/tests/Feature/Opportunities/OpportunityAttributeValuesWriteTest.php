<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-08-05 ("informazioni aggiuntive anche sul form
// opportunita', come sta in gestione richieste"): the opportunities form
// WRITES the dynamic values too, through the very writer the
// request-management channels use (RequestAttributeValueWriter), plus the
// create-time preview endpoint POST /api/opportunities/form-context.
//
// The read side (`attribute_values`/`applicable_attributes` on the detail
// resource, spec 0049 D-8) is covered by OpportunityAttributeValuesResourceTest.

uses(RefreshDatabase::class);

if (! function_exists('attributeWriteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeWriteActor(array $abilities): User
    {
        foreach (['view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('attributeWriteCategory')) {
    /**
     * A ProductCategory carrying one attribute in the Opportunity context.
     *
     * @return array{0: ProductCategory, 1: Attribute}
     */
    function attributeWriteCategory(string $code = 'contract_length', string $type = 'integer'): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
        $attribute = Attribute::factory()->create(['code' => $code, 'name' => 'Contract length', 'type' => $type]);
        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0]);

        return [$category, $attribute];
    }
}

if (! function_exists('attributeWriteMandatoryFks')) {
    /**
     * @return array<string, int>
     */
    function attributeWriteMandatoryFks(): array
    {
        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
        ];
    }
}

it('create: attribute_values are validated against the just-inserted product lines and persisted', function () {
    $actor = attributeWriteActor(['create', 'view']);
    [$category] = attributeWriteCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(attributeWriteMandatoryFks(), [
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
        ],
        'products_of_interest' => [$product->id],
        'attribute_values' => ['contract_length' => 24],
    ]))->assertCreated();

    $response->assertJsonPath('data.attribute_values', ['contract_length' => 24]);
    expect(Opportunity::find($response->json('data.id'))->attribute_values)->toBe(['contract_length' => 24]);
});

it('create: a code outside the applicable set is refused, keyed attribute_values.<code>', function () {
    $actor = attributeWriteActor(['create']);
    [$category] = attributeWriteCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(attributeWriteMandatoryFks(), [
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
        ],
        'products_of_interest' => [$product->id],
        'attribute_values' => ['not_applicable_here' => 'x'],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attribute_values.not_applicable_here']);
});

it('update: the merge is sparse — a code left out keeps its persisted value', function () {
    $actor = attributeWriteActor(['update', 'view']);
    [$category] = attributeWriteCategory();
    [, $other] = attributeWriteCategory('sla_hours', 'integer');
    $category->attributes()->attach($other->id, ['is_required' => false, 'sort_order' => 1]);

    $opportunity = Opportunity::factory()->create([
        'attribute_values' => ['contract_length' => 12, 'sla_hours' => 8],
    ]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'attribute_values' => ['contract_length' => 36],
    ])->assertOk()->assertJsonPath('data.attribute_values', ['contract_length' => 36, 'sla_hours' => 8]);
});

it('update: a PATCH that never mentions attribute_values leaves the map untouched', function () {
    $actor = attributeWriteActor(['update', 'view']);
    $opportunity = Opportunity::factory()->create(['attribute_values' => ['contract_length' => 12]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['success_probability' => 40])
        ->assertOk()
        ->assertJsonPath('data.attribute_values', ['contract_length' => 12]);
});

it('detail: attribute_layout completes the trio and is null with no configured layout', function () {
    $actor = attributeWriteActor(['view']);
    [$category] = attributeWriteCategory();
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.attribute_layout', null)
        ->assertJsonPath('data.applicable_attributes.0.code', 'contract_length');
});

it('form-context: resolves the applicable attributes of the criteria typed so far', function () {
    $actor = attributeWriteActor(['create']);
    [$category] = attributeWriteCategory();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities/form-context', [
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
        ],
    ])->assertOk();

    $response->assertJsonPath('data.applicable_attributes.0.code', 'contract_length');
    $response->assertJsonPath('data.attribute_layout', null);
});

it('form-context: a half-picked product line scopes nothing instead of failing', function () {
    $actor = attributeWriteActor(['create']);
    [$category] = attributeWriteCategory();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities/form-context', [
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => null],
        ],
    ])->assertOk();

    expect($response->json('data.applicable_attributes'))->toBe([]);
});

it('form-context: an actor without opportunities.create is refused (403)', function () {
    $actor = attributeWriteActor(['view']);
    [$category] = attributeWriteCategory();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities/form-context', [
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
        ],
    ])->assertForbidden();
});

<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// "Informazioni aggiuntive" in Gestione Richieste (user directive
// 2026-08-07): the SAME block, context and storage as the Offerte form
// (`AttributeContext::Quote`, values on `quotes.attribute_values`) — only the
// applicable set is resolved differently (D-1: the Opportunity's product
// lines UNION the Offerta's own offer lines), because a request is born with
// no offer lines at all (spec 0086, AC-028).

uses(RefreshDatabase::class);

if (! function_exists('requestAttributesActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestAttributesActor(array $abilities = ['view', 'update', 'create', 'viewAll']): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('requestAttributesCategory')) {
    /**
     * A product category carrying one QUOTE-context attribute, plus a product
     * filed on it (for the offer-line half of the union).
     *
     * @return array{category: ProductCategory, product: Product}
     */
    function requestAttributesCategory(string $code, bool $required = false): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
        $attribute = Attribute::factory()->create(['code' => $code, 'type' => 'text']);
        $category->attributes()->attach($attribute->id, [
            'is_required' => $required,
            'sort_order' => 0,
            'context' => AttributeContext::Quote->value,
        ]);

        return [
            'category' => $category,
            'product' => Product::factory()->create(['category_id' => $category->id]),
        ];
    }
}

if (! function_exists('requestWithProductLineOn')) {
    /** A request (Offerta) whose Opportunity carries one product line on $category. */
    function requestWithProductLineOn(User $supervisor, ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);
        OpportunityProductLine::factory()->for($opportunity)->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
    }
}

// ---------------------------------------------------------------------------
// D-1 — the applicable set comes from the product lines, with no offer line
// ---------------------------------------------------------------------------

it('GET exposes the applicable set resolved from the product lines, with no offer line', function () {
    $actor = requestAttributesActor();
    ['category' => $category] = requestAttributesCategory('preferred_slot');
    $quote = requestWithProductLineOn($actor, $category);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/request-management/{$quote->id}")->assertOk();

    expect($quote->offerLines()->count())->toBe(0)
        ->and(array_column($response->json('data.applicable_attributes'), 'code'))->toBe(['preferred_slot'])
        ->and($response->json('data.attribute_values'))->toBe([]);
});

it('GET unions the offer lines\' own categories into the set (D-1)', function () {
    $actor = requestAttributesActor();
    ['category' => $lineCategory] = requestAttributesCategory('from_product_line');
    ['product' => $offerProduct] = requestAttributesCategory('from_offer_line');
    $quote = requestWithProductLineOn($actor, $lineCategory);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $offerProduct->id]);
    Sanctum::actingAs($actor);

    $codes = array_column(
        $this->getJson("/api/request-management/{$quote->id}")->assertOk()->json('data.applicable_attributes'),
        'code',
    );

    expect($codes)->toContain('from_product_line')->toContain('from_offer_line');
});

// ---------------------------------------------------------------------------
// PATCH — the write lands on the Offerta's own map
// ---------------------------------------------------------------------------

it('PATCH writes an applicable value on quotes.attribute_values and reads it back', function () {
    $actor = requestAttributesActor();
    ['category' => $category] = requestAttributesCategory('preferred_slot');
    $quote = requestWithProductLineOn($actor, $category);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/request-management/{$quote->id}", [
        'attribute_values' => ['preferred_slot' => 'mattina'],
    ])->assertOk();

    expect($response->json('data.attribute_values.preferred_slot'))->toBe('mattina')
        ->and($quote->fresh()->attribute_values)->toBe(['preferred_slot' => 'mattina']);
});

it('PATCH merges sparsely: an omitted code keeps its persisted value', function () {
    $actor = requestAttributesActor();
    ['category' => $category] = requestAttributesCategory('kept');
    $attribute = Attribute::factory()->create(['code' => 'changed', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, [
        'is_required' => false, 'sort_order' => 1, 'context' => AttributeContext::Quote->value,
    ]);
    $quote = requestWithProductLineOn($actor, $category);
    $quote->forceFill(['attribute_values' => ['kept' => 'a', 'changed' => 'b']])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'attribute_values' => ['changed' => 'c'],
    ])->assertOk();

    expect($quote->fresh()->attribute_values)->toBe(['kept' => 'a', 'changed' => 'c']);
});

it('PATCH validates against the set the SAME payload\'s new product lines produce', function () {
    $actor = requestAttributesActor();
    ['category' => $original] = requestAttributesCategory('old_code');
    ['category' => $replacement] = requestAttributesCategory('new_code');
    $quote = requestWithProductLineOn($actor, $original);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
        'attribute_values' => ['new_code' => 'ok'],
    ])->assertOk();

    expect($quote->fresh()->attribute_values)->toBe(['new_code' => 'ok']);
});

it('PATCH without request-management.update -> 403, nothing written', function () {
    $actor = requestAttributesActor(['view', 'viewAll']);
    ['category' => $category] = requestAttributesCategory('preferred_slot');
    $quote = requestWithProductLineOn($actor, $category);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'attribute_values' => ['preferred_slot' => 'mattina'],
    ])->assertForbidden();

    expect($quote->fresh()->attribute_values)->toBeNull();
});

// ---------------------------------------------------------------------------
// POST — the same block at creation, written on the created Offerta
// ---------------------------------------------------------------------------

it('POST writes the submitted values on the created Offerta', function () {
    $actor = requestAttributesActor();
    ['category' => $category] = requestAttributesCategory('preferred_slot');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]],
        'attribute_values' => ['preferred_slot' => 'pomeriggio'],
    ])->assertCreated();

    expect($response->json('data.attribute_values.preferred_slot'))->toBe('pomeriggio')
        ->and(Quote::query()->sole()->attribute_values)->toBe(['preferred_slot' => 'pomeriggio']);
});

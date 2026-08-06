<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0084: the dynamic "Informazioni aggiuntive" section, moved from the
// Opportunity to the Offerta — resolved from THIS quote's own offer lines'
// product categories (D-5), in the `quote` usage context.

uses(RefreshDatabase::class);

if (! function_exists('quoteAttributesUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteAttributesUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteAttributesCategory')) {
    /**
     * A product category (with an effective business function, so a REVENUE
     * line never trips the D-7 coverage guard) carrying one QUOTE-context
     * attribute, plus a product filed on it.
     *
     * @return array{category: ProductCategory, product: Product, attribute: Attribute}
     */
    function quoteAttributesCategory(string $code, string $type = 'text', bool $required = false, array $extra = []): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
        $attribute = Attribute::factory()->create(array_merge(['code' => $code, 'type' => $type], $extra));
        $category->attributes()->attach($attribute->id, [
            'is_required' => $required, 'sort_order' => 0, 'context' => AttributeContext::Quote->value,
        ]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        return ['category' => $category, 'product' => $product, 'attribute' => $attribute];
    }
}

if (! function_exists('createQuoteWithOfferLine')) {
    function createQuoteWithOfferLine(User $actor, Product $product, array $extra = []): TestResponse
    {
        $opportunity = Opportunity::factory()->create();

        return \Pest\Laravel\postJson('/api/quotes', array_merge([
            'title' => 'Offerta',
            'opportunity_id' => $opportunity->id,
            'offer_lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
            ],
        ], $extra));
    }
}

// ---------------------------------------------------------------------------
// AC-010/011 — applicable_attributes: union dedup-per-code, strictest wins
// ---------------------------------------------------------------------------

it('AC-010: applicable_attributes is the union deduped by code across the offer lines\' categories, ordered by sort_order then code', function () {
    ['product' => $productA] = quoteAttributesCategory('field_b', required: false);
    ['product' => $productB] = quoteAttributesCategory('field_a', required: false);
    $actor = quoteAttributesUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $opportunity = Opportunity::factory()->create();
    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertCreated();

    $codes = collect($created->json('data.applicable_attributes'))->pluck('code')->all();
    expect($codes)->toBe(['field_a', 'field_b']);
});

it('AC-011: when the same code is required by one category and optional by another, the merged descriptor is required', function () {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $categoryTwo = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $attribute = Attribute::factory()->create(['code' => 'shared_code', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $categoryTwo->attributes()->attach($attribute->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'quote']);
    $productA = Product::factory()->create(['category_id' => $category->id]);
    $productB = Product::factory()->create(['category_id' => $categoryTwo->id]);
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    $opportunity = Opportunity::factory()->create();
    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 10],
        ],
        'attribute_values' => ['shared_code' => 'value'],
    ])->assertCreated();

    $byCode = collect($created->json('data.applicable_attributes'))->keyBy('code');
    expect($byCode['shared_code']['is_required'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-012/013 — empty/no-layout cases
// ---------------------------------------------------------------------------

it('AC-012: a quote with no offer lines has empty applicable_attributes and a null attribute_layout', function () {
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    $opportunity = Opportunity::factory()->create();
    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated();

    expect($created->json('data.applicable_attributes'))->toBe([])
        ->and($created->json('data.attribute_layout'))->toBeNull();
});

it('AC-013: no contributing category configures a layout -> attribute_layout is null (flat rendering)', function () {
    ['product' => $product] = quoteAttributesCategory('unlaidout_field');
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    $created = createQuoteWithOfferLine($actor, $product)->assertCreated();

    expect($created->json('data.attribute_layout'))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-014 — partial layout + "Altre informazioni" leftover
// ---------------------------------------------------------------------------

it('AC-014: a layout configured on only one of two categories precedes the synthetic "Altre informazioni" leftover section', function () {
    ['category' => $categoryA, 'product' => $productA] = quoteAttributesCategory('laid_out_field');
    ['product' => $productB] = quoteAttributesCategory('leftover_field');

    AttributeLayout::factory()->for($categoryA, 'productCategory')
        ->withCodes(['laid_out_field'], title: 'Configured Section')
        ->create(['context' => AttributeContext::Quote->value, 'form_mode' => LayoutFormScope::All->value]);

    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    $opportunity = Opportunity::factory()->create();
    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertCreated();

    $sections = $created->json('data.attribute_layout.sections');
    expect($sections)->toHaveCount(2);
    expect($sections[0]['title'])->toBe('Configured Section');
    expect($sections[1]['title'])->toBe('Altre informazioni');
});

// ---------------------------------------------------------------------------
// AC-015 — form-context matches what saving the same lines would produce
// ---------------------------------------------------------------------------

it('AC-015: POST /api/quotes/form-context with unsaved offer_lines returns the same set a save would produce', function () {
    ['product' => $product] = quoteAttributesCategory('preview_field');
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    $preview = $this->postJson('/api/quotes/form-context', [
        'offer_lines' => [['product_id' => $product->id]],
    ])->assertOk();

    $saved = createQuoteWithOfferLine($actor, $product)->assertCreated();

    expect($preview->json('data.applicable_attributes'))->toBe($saved->json('data.applicable_attributes'));
});

it('AC-034/D-5: form-context with no product picked yet resolves nothing', function () {
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    $preview = $this->postJson('/api/quotes/form-context', ['offer_lines' => [['product_id' => null]]])->assertOk();

    expect($preview->json('data.applicable_attributes'))->toBe([])
        ->and($preview->json('data.attribute_layout'))->toBeNull();
});

it('form-context: without quotes.create -> 403', function () {
    Sanctum::actingAs(quoteAttributesUserWith([]));

    $this->postJson('/api/quotes/form-context', ['offer_lines' => []])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-020/021/022 — write-side validation
// ---------------------------------------------------------------------------

it('AC-020: a required attribute submitted empty -> 422 keyed attribute_values.<code>', function () {
    ['product' => $product] = quoteAttributesCategory('mandatory_field', required: true);
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    createQuoteWithOfferLine($actor, $product, ['attribute_values' => ['mandatory_field' => '']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.mandatory_field');
});

it('AC-020: a required attribute with attribute_values entirely omitted is NOT enforced (sparse semantics, mirrors update)', function () {
    ['product' => $product] = quoteAttributesCategory('mandatory_field_two', required: true);
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    // No `attribute_values` key at all: the writer never runs (same sparse
    // convention as the update path — AttributeValueValidator only ever
    // validates SUBMITTED codes, never one silently absent from the payload).
    createQuoteWithOfferLine($actor, $product)->assertCreated();
});

it('AC-021: an enum value outside the option set -> 422 keyed attribute_values.<code>', function () {
    ['product' => $product] = quoteAttributesCategory('status_field', type: 'enum');
    Attribute::where('code', 'status_field')->first()->options()->create(['value' => 'open', 'label' => 'Open', 'sort_order' => 0]);
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    createQuoteWithOfferLine($actor, $product, ['attribute_values' => ['status_field' => 'closed']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.status_field');
});

it('AC-022: an invalid date format -> 422; a valid one saves and normalizes', function () {
    ['product' => $product] = quoteAttributesCategory('date_field', type: 'date');
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    createQuoteWithOfferLine($actor, $product, ['attribute_values' => ['date_field' => '31/12/2026']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.date_field');

    $created = createQuoteWithOfferLine($actor, $product, ['attribute_values' => ['date_field' => '2026-12-31']])
        ->assertCreated();

    expect($created->json('data.attribute_values.date_field'))->toBe('2026-12-31');
});

// ---------------------------------------------------------------------------
// AC-023/024 — sparse merge + non-applicable code ignored
// ---------------------------------------------------------------------------

it('AC-023: an update that submits only one of two stored codes leaves the other untouched (sparse merge)', function () {
    ['product' => $product] = quoteAttributesCategory('code_one');
    $categoryTwoProduct = quoteAttributesCategory('code_two');
    $actor = quoteAttributesUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    // A single category carrying BOTH codes, so both are applicable at once.
    $category = ProductCategory::where('id', $product->category_id)->sole();
    $category->attributes()->attach($categoryTwoProduct['attribute']->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);

    $created = createQuoteWithOfferLine($actor, $product, [
        'attribute_values' => ['code_one' => 'first', 'code_two' => 'second'],
    ])->assertCreated();

    $this->patchJson("/api/quotes/{$created->json('data.id')}", [
        'attribute_values' => ['code_one' => 'updated'],
    ])->assertOk()
        ->assertJsonPath('data.attribute_values.code_one', 'updated')
        ->assertJsonPath('data.attribute_values.code_two', 'second');
});

it('AC-024: a code not applicable to the quote is ignored, never persisted', function () {
    ['product' => $product] = quoteAttributesCategory('applicable_field');
    $actor = quoteAttributesUserWith(['create']);
    Sanctum::actingAs($actor);

    // A code from an UNRELATED category never referenced by this quote.
    $otherAttribute = Attribute::factory()->create(['code' => 'not_applicable_here', 'type' => 'text']);
    $otherCategory = ProductCategory::factory()->create();
    $otherCategory->attributes()->attach($otherAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    createQuoteWithOfferLine($actor, $product, [
        'attribute_values' => ['not_applicable_here' => 'x'],
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_values.not_applicable_here');
});

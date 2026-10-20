<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Quotes\QuoteAttributeResolver;
use App\RequestManagement\RequestAttributeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0288 (MT-1): `visible_when` on a layout section — validation (D-2),
// normalization, copy pruning and the multi-category merge (AC-001..AC-004).

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $codes
 * @param  array{attribute_code?: string}|null  $visibleWhen  null = key omitted
 * @return array<string, mixed>
 */
function conditionalSection(string $id, array $codes, ?array $visibleWhen = null, int $sortOrder = 0): array
{
    return [
        'id' => $id,
        'title' => $id,
        'description' => null,
        'variant' => 'default',
        'collapsible' => false,
        'default_collapsed' => false,
        'columns' => 2,
        'sort_order' => $sortOrder,
        'rows' => [[
            'id' => "{$id}-row",
            'items' => array_map(
                static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'],
                $codes,
            ),
        ]],
        ...($visibleWhen === null ? [] : ['visible_when' => $visibleWhen]),
    ];
}

/**
 * A category with a boolean `toggle`, a boolean `other_toggle` and a text
 * `note` attribute in the quote context.
 *
 * @return array{0: ProductCategory, 1: array<int, Attribute>}
 */
function conditionalCategory(): array
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $attributes = [
        Attribute::factory()->create(['code' => 'toggle', 'type' => 'boolean']),
        Attribute::factory()->create(['code' => 'other_toggle', 'type' => 'boolean']),
        Attribute::factory()->create(['code' => 'note', 'type' => 'text']),
    ];

    foreach ($attributes as $index => $attribute) {
        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => $index, 'context' => 'quote']);
    }

    return [$category, $attributes];
}

/**
 * @param  array<int, array<string, mixed>>  $sections
 */
function putConditionalLayout(ProductCategory $category, array $sections): TestResponse
{
    return test()->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'quote',
        'form_mode' => 'create',
        'layout' => ['sections' => $sections],
    ]);
}

// ---------------------------------------------------------------------------
// AC-001 — saved and returned; every section carries the key
// ---------------------------------------------------------------------------

it('AC-001: PUT keeps a valid condition, GET returns it, and a section without the key comes back with null', function () {
    [$category] = conditionalCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $put = putConditionalLayout($category, [
        conditionalSection('controller', ['toggle']),
        conditionalSection('dependent', ['note'], ['attribute_code' => 'toggle'], 1),
    ])->assertOk();

    $put->assertJsonPath('data.layout.sections.0.visible_when', null)
        ->assertJsonPath('data.layout.sections.1.visible_when', ['attribute_code' => 'toggle']);

    $get = $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=quote&form_mode=create")->assertOk();

    expect($get->json('data.layout'))->toBe($put->json('data.layout'));
});

it('AC-001: a condition with extra keys is rebuilt down to attribute_code', function () {
    [$category] = conditionalCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    putConditionalLayout($category, [
        conditionalSection('controller', ['toggle']),
        conditionalSection('dependent', ['note'], ['attribute_code' => 'toggle', 'operator' => 'eq'], 1),
    ])->assertOk()->assertJsonPath('data.layout.sections.1.visible_when', ['attribute_code' => 'toggle']);
});

// ---------------------------------------------------------------------------
// AC-002 — D-2 rules and shape
// ---------------------------------------------------------------------------

it('AC-002: an invalid condition is 422 on attribute_layout naming the code', function (string $failing, string $messagePart) {
    [$category] = conditionalCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $sections = match ($failing) {
        'not placed' => [
            conditionalSection('dependent', ['note'], ['attribute_code' => 'toggle']),
        ],
        'not boolean' => [
            conditionalSection('controller', ['note']),
            conditionalSection('dependent', ['toggle'], ['attribute_code' => 'note'], 1),
        ],
        'same section' => [
            conditionalSection('own', ['toggle', 'note'], ['attribute_code' => 'toggle']),
        ],
        'conditional section' => [
            conditionalSection('root', ['other_toggle']),
            conditionalSection('middle', ['toggle'], ['attribute_code' => 'other_toggle'], 1),
            conditionalSection('leaf', ['note'], ['attribute_code' => 'toggle'], 2),
        ],
    };

    putConditionalLayout($category, $sections)
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_layout')
        ->assertJsonPath('errors.attribute_layout.0', fn (string $message): bool => str_contains($message, $messagePart));

    expect(AttributeLayout::query()->count())->toBe(0);
})->with([
    'code not placed in the layout' => ['not placed', 'not placed in the layout'],
    'non-boolean attribute' => ['not boolean', 'not a boolean'],
    'attribute of the section itself' => ['same section', 'cannot control the section it is placed in'],
    'attribute of a conditional section' => ['conditional section', 'conditional section'],
]);

it('AC-002: a code outside the effective attributes is rejected as not placed or unknown', function () {
    [$category] = conditionalCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    putConditionalLayout($category, [
        conditionalSection('dependent', ['note'], ['attribute_code' => 'ghost']),
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_layout');
});

it('AC-002: a malformed condition is 422 on the section path', function (mixed $visibleWhen, string $errorKey) {
    [$category] = conditionalCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $section = conditionalSection('controller', ['toggle']);
    $section['visible_when'] = $visibleWhen;

    putConditionalLayout($category, [$section])
        ->assertStatus(422)
        ->assertJsonValidationErrors($errorKey);
})->with([
    'string instead of object' => ['toggle', 'layout.sections.0.visible_when'],
    'object without attribute_code' => [['operator' => 'eq'], 'layout.sections.0.visible_when.attribute_code'],
    'attribute_code not a string' => [['attribute_code' => ['toggle']], 'layout.sections.0.visible_when.attribute_code'],
    'attribute_code too long' => [['attribute_code' => str_repeat('a', 192)], 'layout.sections.0.visible_when.attribute_code'],
]);

it('AC-002: PUT with a condition without product-categories.update -> 403', function () {
    [$category] = conditionalCategory();
    Sanctum::actingAs(productCategoryUserWith(['view']));

    putConditionalLayout($category, [
        conditionalSection('controller', ['toggle']),
        conditionalSection('dependent', ['note'], ['attribute_code' => 'toggle'], 1),
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-003 — category duplication
// ---------------------------------------------------------------------------

/**
 * A source category with a conditional layout (`note` shown by `toggle`).
 *
 * @return array{0: ProductCategory, 1: array<int, Attribute>}
 */
function conditionalSourceWithLayout(): array
{
    [$source, $attributes] = conditionalCategory();

    AttributeLayout::query()->create([
        'product_category_id' => $source->id,
        'context' => 'quote',
        'form_mode' => 'all',
        'layout' => ['sections' => [
            conditionalSection('controller', ['toggle']) + ['visible_when' => null],
            conditionalSection('dependent', ['note'], ['attribute_code' => 'toggle'], 1),
        ]],
    ]);

    return [$source, $attributes];
}

it('AC-003: duplicating keeps the condition when its controlling attribute survives', function () {
    [$source, [$toggle, , $note]] = conditionalSourceWithLayout();
    Sanctum::actingAs(productCategoryUserWith(['view', 'create']));

    $id = $this->postJson('/api/product-categories', [
        'name' => 'Copy',
        'attributes' => [
            ['attribute_id' => $toggle->id, 'context' => 'quote'],
            ['attribute_id' => $note->id, 'context' => 'quote'],
        ],
        'layout_source_id' => $source->id,
    ])->assertCreated()->json('data.id');

    $layout = AttributeLayout::query()->where('product_category_id', $id)->firstOrFail()->layout;

    expect($layout['sections'][1]['visible_when'])->toBe(['attribute_code' => 'toggle']);
});

it('AC-003: duplicating clears the condition when its controlling attribute is pruned, and does not fail', function () {
    [$source, [, , $note]] = conditionalSourceWithLayout();
    Sanctum::actingAs(productCategoryUserWith(['view', 'create']));

    $id = $this->postJson('/api/product-categories', [
        'name' => 'Copy',
        'attributes' => [['attribute_id' => $note->id, 'context' => 'quote']],
        'layout_source_id' => $source->id,
    ])->assertCreated()->json('data.id');

    $layout = AttributeLayout::query()->where('product_category_id', $id)->firstOrFail()->layout;

    expect($layout['sections'])->toHaveCount(1)
        ->and($layout['sections'][0]['id'])->toBe('dependent')
        ->and($layout['sections'][0]['visible_when'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-004 — merged layouts (Offerta, Richiesta) keep `visible_when`
// ---------------------------------------------------------------------------

it('AC-004: the merged Offerta and Richiesta layouts keep visible_when on the section', function () {
    [$category] = conditionalCategory();
    AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => AttributeContext::Quote->value,
        'form_mode' => 'all',
        'layout' => ['sections' => [
            conditionalSection('controller', ['toggle']) + ['visible_when' => null],
            conditionalSection('dependent', ['note'], ['attribute_code' => 'toggle'], 1),
        ]],
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $quoteLayout = app(QuoteAttributeResolver::class)->formContext([$product->id], FormMode::Create)['attribute_layout'];
    $requestLayout = app(RequestAttributeResolver::class)->forCategories([$category->id], FormMode::Edit)['attribute_layout'];

    foreach ([$quoteLayout, $requestLayout] as $layout) {
        $conditional = collect($layout['sections'])->firstWhere('id', 'dependent');
        $plain = collect($layout['sections'])->firstWhere('id', 'controller');

        expect($conditional['visible_when'])->toBe(['attribute_code' => 'toggle'])
            ->and($plain['visible_when'])->toBeNull();
    }
});

it('AC-004: a Richiesta resolved from a stored quote keeps visible_when too', function () {
    [$category] = conditionalCategory();
    AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => AttributeContext::Quote->value,
        'form_mode' => 'all',
        'layout' => ['sections' => [
            conditionalSection('controller', ['toggle']),
            conditionalSection('dependent', ['note'], ['attribute_code' => 'toggle'], 1),
        ]],
    ]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id, 'product_category_id' => $category->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $layout = app(RequestAttributeResolver::class)->layout($quote, FormMode::Edit);

    expect(collect($layout['sections'])->firstWhere('id', 'dependent')['visible_when'])->toBe(['attribute_code' => 'toggle']);
});

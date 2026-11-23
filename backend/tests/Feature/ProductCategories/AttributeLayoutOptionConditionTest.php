<?php

declare(strict_types=1);

use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\AttributeOption;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0424: a section opened by ONE option of a choice attribute (D-2),
// the checkbox-list display storing a list (D-1) — layout contract
// (AC-001) and the write pipeline of an offer (AC-003).

uses(RefreshDatabase::class);

const OPTION_CONTROL = 'requested_groups';
const OPTION_NOTE = 'food_note';

/**
 * A quote-context category with a `checklist` enum (options food, energy),
 * a boolean and a REQUIRED text attribute.
 */
function optionConditionCategory(): ProductCategory
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $attributes = [
        Attribute::factory()->create(['code' => OPTION_CONTROL, 'type' => 'enum', 'config' => ['display' => 'checklist']]),
        Attribute::factory()->create(['code' => 'toggle', 'type' => 'boolean']),
        Attribute::factory()->create(['code' => OPTION_NOTE, 'type' => 'text']),
    ];

    foreach (['food', 'energy'] as $index => $value) {
        AttributeOption::query()->create(['attribute_id' => $attributes[0]->id, 'value' => $value, 'label' => ucfirst($value), 'sort_order' => $index]);
    }

    foreach ($attributes as $index => $attribute) {
        $category->attributes()->attach($attribute->id, ['is_required' => $attribute->code === OPTION_NOTE, 'sort_order' => $index, 'context' => 'quote']);
    }

    return $category;
}

/**
 * @param  array<int, string>  $codes
 * @param  array<string, mixed>|null  $visibleWhen
 * @return array<string, mixed>
 */
function optionSection(string $id, array $codes, ?array $visibleWhen = null, int $sortOrder = 0): array
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
        'visible_when' => $visibleWhen,
        'rows' => [[
            'id' => "{$id}-row",
            'items' => array_map(static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'], $codes),
        ]],
    ];
}

/**
 * @param  array<string, mixed>  $visibleWhen
 */
function putOptionLayout(ProductCategory $category, array $visibleWhen): TestResponse
{
    return test()->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'quote',
        'form_mode' => 'create',
        'layout' => ['sections' => [
            optionSection('controls', [OPTION_CONTROL, 'toggle']),
            optionSection('dependent', [OPTION_NOTE], $visibleWhen, 1),
        ]],
    ]);
}

it('AC-001: a section conditioned on an option of a choice attribute is saved and returned', function (): void {
    $category = optionConditionCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    putOptionLayout($category, ['attribute_code' => OPTION_CONTROL, 'option_value' => 'food'])
        ->assertOk()
        ->assertJsonPath('data.layout.sections.1.visible_when', ['attribute_code' => OPTION_CONTROL, 'option_value' => 'food']);

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=quote&form_mode=create")
        ->assertOk()
        ->assertJsonPath('data.layout.sections.1.visible_when.option_value', 'food');
});

it('AC-001: a yes/no condition keeps its shape, an empty option included', function (): void {
    $category = optionConditionCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    putOptionLayout($category, ['attribute_code' => 'toggle', 'option_value' => ''])
        ->assertOk()
        ->assertJsonPath('data.layout.sections.1.visible_when', ['attribute_code' => 'toggle']);
});

it('AC-001: an option condition that does not fit its attribute is 422 on attribute_layout', function (array $visibleWhen, string $messagePart): void {
    $category = optionConditionCategory();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    putOptionLayout($category, $visibleWhen)
        ->assertStatus(422)
        ->assertJsonPath('errors.attribute_layout.0', fn (string $message): bool => str_contains($message, $messagePart));

    expect(AttributeLayout::query()->count())->toBe(0);
})->with([
    'choice without option' => [['attribute_code' => OPTION_CONTROL], 'must name one of its options'],
    'option of another attribute' => [['attribute_code' => OPTION_CONTROL, 'option_value' => 'ghost'], 'does not belong'],
    'yes/no with an option' => [['attribute_code' => 'toggle', 'option_value' => 'food'], 'takes no option'],
]);

/**
 * An offer writer and the category's layout, written on the model.
 */
function optionQuoteSurface(): Product
{
    $user = User::factory()->create();

    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        $user->givePermissionTo(Permission::findOrCreate("quotes.{$ability}"));
    }

    Sanctum::actingAs($user);
    $category = optionConditionCategory();

    AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => 'quote',
        'form_mode' => LayoutFormScope::All->value,
        'layout' => ['sections' => [
            optionSection('controls', [OPTION_CONTROL, 'toggle']),
            optionSection('dependent', [OPTION_NOTE], ['attribute_code' => OPTION_CONTROL, 'option_value' => 'food'], 1),
        ]],
    ]);

    return Product::factory()->create(['category_id' => $category->id]);
}

function storeOptionQuote(Product $product, array $values): TestResponse
{
    return test()->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
        'attribute_values' => $values,
    ]);
}

it('AC-003: a checkbox list stores its ticked options; the section of a ticked option requires its fields', function (): void {
    $product = optionQuoteSurface();

    storeOptionQuote($product, [OPTION_CONTROL => ['food'], OPTION_NOTE => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.'.OPTION_NOTE);

    storeOptionQuote($product, [OPTION_CONTROL => ['energy', 'food'], OPTION_NOTE => 'Filled'])->assertCreated();

    expect(Quote::query()->latest('id')->firstOrFail()->attribute_values)
        ->toMatchArray([OPTION_CONTROL => ['energy', 'food'], OPTION_NOTE => 'Filled']);
});

it('AC-003: an option outside the list is 422', function (): void {
    $product = optionQuoteSurface();

    storeOptionQuote($product, [OPTION_CONTROL => ['ghost']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.'.OPTION_CONTROL);
});

it('AC-003: unticking the option empties its section, without the required rule blocking', function (): void {
    $product = optionQuoteSurface();
    storeOptionQuote($product, [OPTION_CONTROL => ['food'], OPTION_NOTE => 'Filled'])->assertCreated();
    $quote = Quote::query()->latest('id')->firstOrFail();

    $this->patchJson("/api/quotes/{$quote->id}", ['attribute_values' => [OPTION_CONTROL => ['energy']]])->assertOk();

    expect($quote->fresh()->attribute_values)->toMatchArray([OPTION_CONTROL => ['energy'], OPTION_NOTE => null]);
});

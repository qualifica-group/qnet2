<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategories\AttributeLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0115: a category with no layout of its own renders its nearest
// inheriting ancestor's, under the attribute barrier (AC-001..012).

uses(RefreshDatabase::class);

// Guarded redefinitions, the convention every tests/Feature/ProductCategories
// file already follows: whichever file Pest loads first defines them.
if (! function_exists('productCategoryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productCategoryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("product-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-categories.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('attributeLayoutBlob')) {
    /**
     * A minimal, contract-valid layout blob placing each of $codes (in
     * order) as a `half`-width item in a single row/section.
     *
     * @param  array<int, string>  $codes
     * @return array<string, mixed>
     */
    function attributeLayoutBlob(array $codes): array
    {
        return [
            'sections' => [[
                'id' => 'sec-1',
                'title' => 'General',
                'description' => 'A description',
                'variant' => 'default',
                'collapsible' => true,
                'default_collapsed' => false,
                'columns' => 2,
                'sort_order' => 0,
                'rows' => [[
                    'id' => 'row-1',
                    'items' => array_map(
                        static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'],
                        $codes,
                    ),
                ]],
            ]],
        ];
    }
}

/**
 * A category carrying $code as a `quote` attribute, under $parent.
 */
function inheritanceCategory(string $name, string $code, ?ProductCategory $parent = null): ProductCategory
{
    $category = ProductCategory::factory()->create([
        'name' => $name,
        'parent_id' => $parent?->id,
    ]);

    $attribute = Attribute::query()->firstOrCreate(['code' => $code], ['name' => $code, 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    return $category;
}

function layoutRow(ProductCategory $category, LayoutFormScope $scope, array $codes, string $context = 'quote'): void
{
    AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => $context,
        'form_mode' => $scope->value,
        'layout' => attributeLayoutBlob($codes),
    ]);
}

function resolveQuoteLayout(ProductCategory $category, FormMode $mode = FormMode::Create): ?array
{
    return app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, $mode);
}

// ---------------------------------------------------------------------------
// AC-001..007 — the resolution itself
// ---------------------------------------------------------------------------

it('AC-001: a child with no layout renders its ancestor shared layout', function () {
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('Yisu', 'extra_code', $parent);
    layoutRow($parent, LayoutFormScope::All, ['total_hours']);

    expect(resolveQuoteLayout($child))->toBe(attributeLayoutBlob(['total_hours']));
});

it('AC-002: the child own shared layout beats an ancestor per-mode override', function () {
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('Yisu', 'extra_code', $parent);
    layoutRow($parent, LayoutFormScope::Create, ['total_hours']);
    layoutRow($child, LayoutFormScope::All, ['extra_code']);

    expect(resolveQuoteLayout($child))->toBe(attributeLayoutBlob(['extra_code']));
});

it('AC-003: within one ancestor the per-mode override beats its shared layout', function () {
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('Yisu', 'extra_code', $parent);
    layoutRow($parent, LayoutFormScope::All, ['total_hours']);
    layoutRow($parent, LayoutFormScope::Create, ['extra_code']);

    expect(resolveQuoteLayout($child, FormMode::Create))->toBe(attributeLayoutBlob(['extra_code']));
    expect(resolveQuoteLayout($child, FormMode::Edit))->toBe(attributeLayoutBlob(['total_hours']));
});

it('AC-004: a child with the quote barrier down inherits no layout', function () {
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('DIL', 'chosen_course', $parent);
    $child->update(['inherits_quote_attributes' => false]);
    layoutRow($parent, LayoutFormScope::All, ['total_hours']);

    expect(resolveQuoteLayout($child->fresh()))->toBeNull();
});

it('AC-005: the chain stops at a barrier ancestor that has no layout of its own', function () {
    $grandparent = inheritanceCategory('Formazione', 'total_hours');
    $parent = inheritanceCategory('DIL', 'chosen_course', $grandparent);
    $parent->update(['inherits_quote_attributes' => false]);
    $child = inheritanceCategory('DIL - Sub', 'sub_code', $parent->fresh());
    layoutRow($grandparent, LayoutFormScope::All, ['total_hours']);

    expect(resolveQuoteLayout($child))->toBeNull();
});

it('AC-006: the three context barriers are independent', function () {
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('Yisu', 'extra_code', $parent);
    $child->update(['inherits_quote_attributes' => false, 'inherits_product_attributes' => true]);

    $productAttribute = Attribute::query()->firstOrCreate(['code' => 'sku'], ['name' => 'sku', 'type' => 'text']);
    $parent->attributes()->attach($productAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    layoutRow($parent, LayoutFormScope::All, ['total_hours']);
    layoutRow($parent, LayoutFormScope::All, ['sku'], 'product');

    $service = app(AttributeLayoutService::class);
    $child = $child->fresh();

    expect($service->resolveWithFallback($child, AttributeContext::Quote, FormMode::Create))->toBeNull();
    expect($service->resolveWithFallback($child, AttributeContext::Product, FormMode::Create))
        ->toBe(attributeLayoutBlob(['sku']));
});

it('AC-007: a chain with no layout anywhere still resolves to flat rendering', function () {
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('Yisu', 'extra_code', $parent);

    expect(resolveQuoteLayout($child))->toBeNull();
});

it('AC-008: resolving a deep chain costs the same number of queries as a shallow one', function () {
    $root = inheritanceCategory('Formazione', 'total_hours');
    layoutRow($root, LayoutFormScope::All, ['total_hours']);

    $shallow = inheritanceCategory('Level 1', 'code_1', $root);
    $deep = $shallow;
    foreach (range(2, 5) as $level) {
        $deep = inheritanceCategory("Level {$level}", "code_{$level}", $deep);
    }

    $count = function (ProductCategory $category): int {
        // A fresh service per measurement: the hierarchy memo is per instance,
        // and sharing it would hide the very cost being measured.
        $service = app()->makeWith(AttributeLayoutService::class, []);
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $service->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create);

        return $queries;
    };

    expect($count($deep))->toBe($count($shallow));
});

// ---------------------------------------------------------------------------
// AC-010..012 — the authoring endpoint
// ---------------------------------------------------------------------------

it('AC-010: the authoring load names the ancestor a category inherits from', function () {
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('Yisu', 'extra_code', $parent);
    layoutRow($parent, LayoutFormScope::All, ['total_hours']);

    $response = $this
        ->getJson("/api/product-categories/{$child->id}/attribute-layouts?context=quote&exact=1")
        ->assertOk();

    expect($response->json('data.layout'))->toBeNull();
    expect($response->json('data.inherited_from_category'))->toBe(attributeLayoutBlob(['total_hours']));
    $response->assertJsonPath('data.inherited_from_category_source.id', $parent->id);
    $response->assertJsonPath('data.inherited_from_category_source.name', 'Formazione');
});

it('AC-011: a category that inherits nothing reports both new fields as null', function () {
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));
    $root = inheritanceCategory('Formazione', 'total_hours');
    layoutRow($root, LayoutFormScope::All, ['total_hours']);

    $response = $this
        ->getJson("/api/product-categories/{$root->id}/attribute-layouts?context=quote&exact=1")
        ->assertOk();

    // The root answers for itself: its own row is the layout, nothing is inherited.
    expect($response->json('data.layout'))->toBe(attributeLayoutBlob(['total_hours']));
    expect($response->json('data.inherited_from_category'))->toBeNull();
    expect($response->json('data.inherited_from_category_source'))->toBeNull();
});

it('AC-012: deleting a category own layout hands it back to the ancestor', function () {
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));
    $parent = inheritanceCategory('Formazione', 'total_hours');
    $child = inheritanceCategory('Yisu', 'total_hours_child', $parent);
    layoutRow($parent, LayoutFormScope::All, ['total_hours']);
    layoutRow($child, LayoutFormScope::All, ['total_hours_child']);

    $this->putJson("/api/product-categories/{$child->id}/attribute-layouts", [
        'context' => 'quote',
        'form_mode' => 'all',
        'layout' => null,
    ])->assertOk();

    $response = $this
        ->getJson("/api/product-categories/{$child->id}/attribute-layouts?context=quote&exact=1")
        ->assertOk();

    expect($response->json('data.layout'))->toBeNull();
    expect($response->json('data.inherited_from_category'))->toBe(attributeLayoutBlob(['total_hours']));
    $response->assertJsonPath('data.inherited_from_category_source.id', $parent->id);
});

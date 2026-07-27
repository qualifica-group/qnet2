<?php

declare(strict_types=1);

use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0062: GET/PUT /api/product-categories/{productCategory}/attribute-layouts
// (AC-001..006).

uses(RefreshDatabase::class);

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

// ---------------------------------------------------------------------------
// AC-001 — migration/schema
// ---------------------------------------------------------------------------

it('AC-001: attribute_layouts table exists with the contracted columns and unique constraint', function () {
    expect(Schema::hasTable('attribute_layouts'))->toBeTrue();
    expect(Schema::hasColumns('attribute_layouts', [
        'id', 'product_category_id', 'context', 'form_mode', 'layout', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $category = ProductCategory::factory()->create();

    AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => null,
    ]);

    expect(fn () => AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => null,
    ]))->toThrow(QueryException::class);
});

it('AC-001: deleting the category cascades its attribute layouts', function () {
    $category = ProductCategory::factory()->create();
    AttributeLayout::factory()->for($category, 'productCategory')->create();

    $category->delete();

    expect(AttributeLayout::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-002 — PUT valid -> upsert; GET returns the same normalized blob; idempotent
// ---------------------------------------------------------------------------

it('AC-002: PUT with a valid layout upserts, GET returns the same normalized blob, and a repeat PUT stays one row', function () {
    $actor = productCategoryUserWith(['view', 'update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $payload = [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => attributeLayoutBlob(['material']),
    ];

    $first = $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", $payload)->assertOk();
    $first->assertJsonPath('data.layout.sections.0.rows.0.items.0.attribute_code', 'material');

    $get = $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=opportunity&form_mode=create")->assertOk();
    expect($get->json('data.layout'))->toBe($first->json('data.layout'));

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", $payload)->assertOk();

    expect(AttributeLayout::query()->where('product_category_id', $category->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-003 — unknown attribute_code -> 422, keyed attribute_layout, no write
// ---------------------------------------------------------------------------

it('AC-003: PUT with an attribute_code outside the category effective set -> 422 keyed attribute_layout, no write', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => attributeLayoutBlob(['not_a_real_code']),
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_layout');

    expect(AttributeLayout::query()->count())->toBe(0);
});

it('AC-003: a Product-context-only code is rejected for an Opportunity-context PUT (per-context allow-list)', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'product_only']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => attributeLayoutBlob(['product_only']),
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_layout');
});

// ---------------------------------------------------------------------------
// AC-004 — duplicate code / enum-out-of-range -> 422
// ---------------------------------------------------------------------------

it('AC-004: PUT with the same attribute_code placed twice -> 422', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => attributeLayoutBlob(['material', 'material']),
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_layout');
});

it('AC-004: PUT with an out-of-enum section variant -> 422', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $blob = attributeLayoutBlob(['material']);
    $blob['sections'][0]['variant'] = 'bogus';

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => $blob,
    ])->assertStatus(422);
});

it('AC-004: PUT with an out-of-enum item width -> 422', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $blob = attributeLayoutBlob(['material']);
    $blob['sections'][0]['rows'][0]['items'][0]['width'] = 'bogus';

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => $blob,
    ])->assertStatus(422);
});

it('AC-004: PUT with columns outside {1,2,3,4} -> 422', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $blob = attributeLayoutBlob(['material']);
    $blob['sections'][0]['columns'] = 5;

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => $blob,
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-005 — authz + 404
// ---------------------------------------------------------------------------

it('AC-005: PUT without product-categories.update -> 403', function () {
    $actor = productCategoryUserWith(['view']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => null,
    ])->assertStatus(403);
});

it('AC-005: GET without product-categories.view -> 403', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts")->assertStatus(403);
});

it('AC-005: an inexistent category -> 404 on GET and PUT', function () {
    $actor = productCategoryUserWith(['view', 'update']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/999999/attribute-layouts')->assertStatus(404);
    $this->putJson('/api/product-categories/999999/attribute-layouts', [
        'context' => 'opportunity', 'form_mode' => 'create', 'layout' => null,
    ])->assertStatus(404);
});

// ---------------------------------------------------------------------------
// AC-006 — GET with no configured layout
// ---------------------------------------------------------------------------

it('AC-006: GET on a category with no layout -> data.layout is null, data.attributes is the effective set', function () {
    $actor = productCategoryUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'opp_field']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$category->id}/attribute-layouts")->assertOk();

    expect($response->json('data.layout'))->toBeNull();
    expect(collect($response->json('data.attributes'))->pluck('code')->all())->toBe(['opp_field']);
});

it('accepts the quarter item width (single cell of a 4-column section) and round-trips it', function () {
    $actor = productCategoryUserWith(['view', 'update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $blob = attributeLayoutBlob(['material']);
    $blob['sections'][0]['columns'] = 4;
    $blob['sections'][0]['rows'][0]['items'][0]['width'] = 'quarter';

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => $blob,
    ])->assertOk()->assertJsonPath('data.layout.sections.0.rows.0.items.0.width', 'quarter');
});

// ---------------------------------------------------------------------------
// Shared scope vs per-mode override (spec 0062, D3 revised)
// ---------------------------------------------------------------------------

if (! function_exists('categoryWithScopedLayout')) {
    /**
     * A category exposing $codes in the PRODUCT context, with those same codes
     * laid out under the $scope row.
     *
     * @param  array<int, string>  $codes
     */
    function categoryWithScopedLayout(string $scope, array $codes = ['material']): ProductCategory
    {
        $category = ProductCategory::factory()->create();

        foreach ($codes as $code) {
            $attribute = Attribute::factory()->create(['code' => $code]);
            $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
        }

        AttributeLayout::factory()->for($category, 'productCategory')
            ->withCodes($codes)
            ->create(['context' => 'product', 'form_mode' => $scope]);

        return $category;
    }
}

it('GET for a mode with no override falls back to the shared layout (product form path)', function () {
    $category = categoryWithScopedLayout('all');
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=product&form_mode=edit")
        ->assertOk()
        ->assertJsonPath('data.layout.sections.0.rows.0.items.0.attribute_code', 'material');
});

it('a per-mode override wins over the shared layout (product form path)', function () {
    $category = categoryWithScopedLayout('all', ['material', 'colour']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['colour'])
        ->create(['context' => 'product', 'form_mode' => 'edit']);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=product&form_mode=edit")
        ->assertOk()
        ->assertJsonPath('data.layout.sections.0.rows.0.items.0.attribute_code', 'colour');
});

it('a layout scoped to ONE mode never leaks into another mode (no implicit cross-mode fallback)', function () {
    $category = categoryWithScopedLayout('create');
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=product&form_mode=edit")
        ->assertOk()
        ->assertJsonPath('data.layout', null);
});

it('GET with exact=1 on a mode with no override returns a null layout and the shared one as `inherited`', function () {
    $category = categoryWithScopedLayout('all');
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=product&form_mode=edit&exact=1")
        ->assertOk()
        ->assertJsonPath('data.layout', null)
        ->assertJsonPath('data.inherited.sections.0.rows.0.items.0.attribute_code', 'material');
});

it('GET with exact=1 on the shared scope returns its own layout and no `inherited`', function () {
    $category = categoryWithScopedLayout('all');
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=product&form_mode=all&exact=1")
        ->assertOk()
        ->assertJsonPath('data.layout.sections.0.rows.0.items.0.attribute_code', 'material')
        ->assertJsonPath('data.inherited', null);
});

it('a consuming GET (no exact) rejects the shared scope: `all` is not a renderable form mode', function () {
    $category = categoryWithScopedLayout('all');
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$category->id}/attribute-layouts?context=product&form_mode=all")
        ->assertStatus(422)
        ->assertJsonValidationErrors('form_mode');
});

it('PUT persists the shared scope as its own row, alongside a per-mode override', function () {
    $category = categoryWithScopedLayout('all');
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'product',
        'form_mode' => 'view',
        'layout' => attributeLayoutBlob(['material']),
    ])->assertOk();

    expect(AttributeLayout::query()->where('product_category_id', $category->id)->pluck('form_mode')->all())
        ->toEqualCanonicalizing([LayoutFormScope::All, LayoutFormScope::View]);
});

it('PUT with layout=null on a per-mode scope drops only that override, leaving the shared layout', function () {
    $category = categoryWithScopedLayout('all');
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'product', 'form_mode' => 'view']);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'product',
        'form_mode' => 'view',
        'layout' => null,
    ])->assertOk()->assertJsonPath('data.layout', null);

    expect(AttributeLayout::query()->where('product_category_id', $category->id)->pluck('form_mode')->all())
        ->toBe([LayoutFormScope::All]);
});

// ---------------------------------------------------------------------------
// PUT with layout=null (or empty sections) deletes the row (back to flat)
// ---------------------------------------------------------------------------

it('PUT with layout=null deletes an existing row, back to flat', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    AttributeLayout::factory()->for($category, 'productCategory')->create(['context' => 'opportunity', 'form_mode' => 'create']);

    Sanctum::actingAs($actor);

    $response = $this->putJson("/api/product-categories/{$category->id}/attribute-layouts", [
        'context' => 'opportunity',
        'form_mode' => 'create',
        'layout' => null,
    ])->assertOk();

    expect($response->json('data.layout'))->toBeNull();
    expect(AttributeLayout::query()->where('product_category_id', $category->id)->count())->toBe(0);
});

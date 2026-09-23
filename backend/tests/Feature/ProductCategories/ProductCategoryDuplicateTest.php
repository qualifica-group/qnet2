<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Row action "duplicate": the grid offers it to an actor who may create
// categories, and POST /api/product-categories with `layout_source_id` copies
// the source's own attribute layouts onto the new category.

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

/**
 * One section holding $rowsOfCodes, one layout row per inner list.
 *
 * @param  array<int, array<int, string>>  $rowsOfCodes
 * @return array<string, mixed>
 */
function duplicateLayoutBlob(array $rowsOfCodes): array
{
    return [
        'sections' => [[
            'id' => 'sec-1',
            'title' => 'General',
            'description' => null,
            'variant' => 'default',
            'collapsible' => false,
            'default_collapsed' => false,
            'columns' => 2,
            'sort_order' => 0,
            'rows' => array_map(
                static fn (array $codes, int $index): array => [
                    'id' => "row-{$index}",
                    'items' => array_map(static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'], $codes),
                ],
                $rowsOfCodes,
                array_keys($rowsOfCodes),
            ),
        ]],
    ];
}

/**
 * A source category owning two quote-context attributes and a shared plus a
 * per-mode layout over them.
 *
 * @return array{0: ProductCategory, 1: Attribute, 2: Attribute}
 */
function duplicateSourceWithLayouts(): array
{
    $source = ProductCategory::factory()->create(['name' => 'Source']);
    $material = Attribute::factory()->create(['code' => 'material']);
    $color = Attribute::factory()->create(['code' => 'color']);
    $source->attributes()->attach($material->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $source->attributes()->attach($color->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);

    AttributeLayout::query()->create([
        'product_category_id' => $source->id,
        'context' => 'quote',
        'form_mode' => 'all',
        'layout' => duplicateLayoutBlob([['material'], ['color']]),
    ]);
    AttributeLayout::query()->create([
        'product_category_id' => $source->id,
        'context' => 'quote',
        'form_mode' => 'view',
        'layout' => duplicateLayoutBlob([['color', 'material']]),
    ]);

    return [$source, $material, $color];
}

/**
 * @return array<int, array<string, mixed>>
 */
function duplicateAssignments(Attribute ...$attributes): array
{
    return array_map(
        static fn (Attribute $attribute): array => ['attribute_id' => $attribute->id, 'context' => 'quote'],
        $attributes,
    );
}

it('row.actions contains duplicate only for an actor with product-categories.create', function (array $abilities, bool $expected) {
    ProductCategory::factory()->create();
    Sanctum::actingAs(productCategoryUserWith($abilities));

    $actions = $this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items.0.actions');

    expect(in_array('duplicate', $actions, true))->toBe($expected);
})->with([
    'with create' => [['viewAny', 'create'], true],
    'without create' => [['viewAny', 'view', 'update'], false],
]);

it('catalogue declares the duplicate action with the shared label and icon', function () {
    Sanctum::actingAs(productCategoryUserWith(['viewAny', 'create']));

    $entry = collect($this->getJson('/api/tables/product-categories/columns')->assertOk()->json('data.actions'))
        ->firstWhere('key', 'duplicate');

    expect($entry)->toMatchArray(['label' => 'actions.duplicate', 'icon' => 'copy', 'type' => 'action', 'confirm' => false]);
});

it('create with layout_source_id copies the source layouts verbatim when the attribute set is unchanged', function () {
    [$source, $material, $color] = duplicateSourceWithLayouts();
    Sanctum::actingAs(productCategoryUserWith(['view', 'create']));

    $id = $this->postJson('/api/product-categories', [
        'name' => 'Source (copia)',
        'attributes' => duplicateAssignments($material, $color),
        'layout_source_id' => $source->id,
    ])->assertCreated()->json('data.id');

    $layoutOf = static fn (int $categoryId, string $scope): ?array => AttributeLayout::query()
        ->where('product_category_id', $categoryId)->where('context', 'quote')->where('form_mode', $scope)->first()?->layout;

    expect($layoutOf($id, 'all'))->toBe($layoutOf($source->id, 'all'))
        ->and($layoutOf($id, 'view'))->toBe($layoutOf($source->id, 'view'))
        ->and(AttributeLayout::query()->where('product_category_id', $source->id)->count())->toBe(2);
});

it('create with layout_source_id prunes the items whose attribute the copy no longer has, and drops emptied rows', function () {
    [$source, $material] = duplicateSourceWithLayouts();
    Sanctum::actingAs(productCategoryUserWith(['view', 'create']));

    $id = $this->postJson('/api/product-categories', [
        'name' => 'Source (copia)',
        'attributes' => duplicateAssignments($material),
        'layout_source_id' => $source->id,
    ])->assertCreated()->json('data.id');

    $shared = AttributeLayout::query()->where('product_category_id', $id)->where('form_mode', 'all')->firstOrFail()->layout;
    $perMode = AttributeLayout::query()->where('product_category_id', $id)->where('form_mode', 'view')->firstOrFail()->layout;

    expect($shared['sections'][0]['rows'])->toHaveCount(1)
        ->and($shared['sections'][0]['rows'][0]['items'])->toBe([['attribute_code' => 'material', 'width' => 'half']])
        ->and($perMode['sections'][0]['rows'][0]['items'])->toBe([['attribute_code' => 'material', 'width' => 'half']]);
});

it('create with layout_source_id writes no layout when none of its attributes survive on the copy', function () {
    [$source] = duplicateSourceWithLayouts();
    Sanctum::actingAs(productCategoryUserWith(['view', 'create']));

    $id = $this->postJson('/api/product-categories', [
        'name' => 'Source (copia)',
        'layout_source_id' => $source->id,
    ])->assertCreated()->json('data.id');

    expect(AttributeLayout::query()->where('product_category_id', $id)->exists())->toBeFalse();
});

it('create with layout_source_id is 403 without product-categories.view and writes nothing', function () {
    [$source, $material, $color] = duplicateSourceWithLayouts();
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Source (copia)',
        'attributes' => duplicateAssignments($material, $color),
        'layout_source_id' => $source->id,
    ])->assertForbidden();

    expect(ProductCategory::query()->count())->toBe(1)
        ->and(AttributeLayout::query()->count())->toBe(2);
});

it('create with a non-existent layout_source_id is 422', function () {
    Sanctum::actingAs(productCategoryUserWith(['view', 'create']));

    $this->postJson('/api/product-categories', ['name' => 'Copy', 'layout_source_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('layout_source_id');
});

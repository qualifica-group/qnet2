<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Column-layout persistence of the `attr.<code>` flexible columns across the
// category tabs. The layout is ONE per-domain row while each tab only shows
// (and so only saves) its own category's attribute columns: a save must never
// discard what another tab customized, and the default baseline an attribute
// column is diffed against must match the shape the tab actually shows.

uses(RefreshDatabase::class);

if (! function_exists('attributePreferencesUser')) {
    function attributePreferencesUser(): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.viewAll']);

        return $user;
    }
}

if (! function_exists('attributePreferencesCategory')) {
    function attributePreferencesCategory(string $code): ProductCategory
    {
        $category = ProductCategory::factory()->create();
        $attribute = Attribute::factory()->ofType('text')->create(['code' => $code]);

        $category->attributes()->attach($attribute->id, [
            'is_required' => false,
            'sort_order' => 0,
            'context' => 'quote',
        ]);

        return $category;
    }
}

if (! function_exists('attributePreferencesColumn')) {
    /**
     * @return array<string, mixed>|null
     */
    function attributePreferencesColumn(object $test, string $columnId, ?int $categoryId): ?array
    {
        $query = $categoryId === null ? '' : "?product_category_id={$categoryId}";
        $columns = $test->getJson("/api/tables/request-management/columns{$query}")->assertOk()->json('data.columns');

        return collect($columns)->firstWhere('id', $columnId);
    }
}

it('keeps an attribute column hidden after reload', function () {
    $category = attributePreferencesCategory('field_a');
    Sanctum::actingAs(attributePreferencesUser());

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [['id' => 'attr.field_a', 'visible' => false, 'order' => 0]],
        'product_category_id' => $category->id,
    ])->assertOk();

    expect(attributePreferencesColumn($this, 'attr.field_a', $category->id)['visible'])->toBeFalse();
});

it('keeps a tab attribute column layout when another tab saves its own', function () {
    $categoryA = attributePreferencesCategory('field_a');
    $categoryB = attributePreferencesCategory('field_b');
    Sanctum::actingAs(attributePreferencesUser());

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [['id' => 'attr.field_a', 'visible' => true, 'width' => 320, 'order' => 0]],
        'product_category_id' => $categoryA->id,
    ])->assertOk();

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [['id' => 'attr.field_b', 'visible' => true, 'width' => 280, 'order' => 0]],
        'product_category_id' => $categoryB->id,
    ])->assertOk();

    expect(attributePreferencesColumn($this, 'attr.field_a', $categoryA->id)['width'])->toBe(320)
        ->and(attributePreferencesColumn($this, 'attr.field_b', $categoryB->id)['width'])->toBe(280);
});

it('keeps attribute column layouts when the Tutte tab saves', function () {
    $category = attributePreferencesCategory('field_a');
    Sanctum::actingAs(attributePreferencesUser());

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [['id' => 'attr.field_a', 'visible' => false, 'width' => 320, 'order' => 0]],
        'product_category_id' => $category->id,
    ])->assertOk();

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [['id' => 'source', 'visible' => false, 'order' => 0]],
    ])->assertOk();

    expect(attributePreferencesColumn($this, 'attr.field_a', $category->id))
        ->visible->toBeFalse()
        ->width->toBe(320)
        ->and(attributePreferencesColumn($this, 'source', null)['visible'])->toBeFalse();
});

it('drops a submitted column override once it is back to the default', function () {
    $category = attributePreferencesCategory('field_a');
    Sanctum::actingAs(attributePreferencesUser());

    foreach ([false, true] as $visible) {
        $this->postJson('/api/tables/request-management/preferences', [
            'columns' => [['id' => 'attr.field_a', 'visible' => $visible]],
            'product_category_id' => $category->id,
        ])->assertOk();
    }

    expect(attributePreferencesColumn($this, 'attr.field_a', $category->id)['visible'])->toBeTrue();
});

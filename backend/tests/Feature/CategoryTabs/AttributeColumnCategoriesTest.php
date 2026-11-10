<?php

declare(strict_types=1);

use App\Models\ProductCategory;
use App\Tables\CategoryTabs\AttributeColumnCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0374, D-1, D-9, D-10: the categories whose flexible fields a grid shows (AC-001, AC-019).

uses(RefreshDatabase::class);

function attributeColumnsFor(array $values): array
{
    return app(AttributeColumnCategories::class)->fromFilters($values);
}

it('deduces the categories from the product and parent category filters (AC-001)', function () {
    $roots = ProductCategory::factory()->count(3)->create();
    [$a, $b, $r] = $roots->pluck('id')->all();

    expect(attributeColumnsFor(['product_category' => [$a]]))->toBe([$a])
        ->and(attributeColumnsFor(['product_category' => [$a], 'parent_category' => []]))->toBe([$a])
        ->and(attributeColumnsFor(['product_category' => [$a], 'parent_category' => [$r, $b]]))->toBe([$a])
        ->and(attributeColumnsFor(['product_category' => [$a], 'parent_category' => [0]]))->toBe([$a])
        ->and(attributeColumnsFor(['product_category' => [$a, $b], 'parent_category' => [$r]]))->toBe([])
        ->and(attributeColumnsFor(['parent_category' => [$r]]))->toBe([$r])
        ->and(attributeColumnsFor(['parent_category' => [$r, $a]]))->toBe([])
        ->and(attributeColumnsFor(['parent_category' => [0]]))->toBe([])
        ->and(attributeColumnsFor(['parent_category' => [$r, 0]]))->toBe([])
        ->and(attributeColumnsFor([]))->toBe([])
        ->and(attributeColumnsFor(['product_category' => [], 'parent_category' => []]))->toBe([]);
});

// REQUIREMENT CHANGED (spec 0374 rev.3, D-12): in the parent category a root stands for its whole group.
it('takes the whole group from the parent category and the rev.2 rule from the product category (AC-019)', function () {
    $current = ProductCategory::factory()->create();
    $first = categoryTabLegacyRoot($current);
    $second = categoryTabLegacyRoot($current);
    $unlinked = ProductCategory::factory()->create();
    $legacyUnlinked = categoryTabLegacyRoot();
    [$r, $l1, $l2] = [$current->id, $first->id, $second->id];

    expect(attributeColumnsFor(['parent_category' => [$r]]))->toBe([$r, $l1, $l2])
        ->and(attributeColumnsFor(['parent_category' => [$l1]]))->toBe([$r, $l1, $l2])
        ->and(attributeColumnsFor(['parent_category' => [$l1, $r]]))->toBe([$r, $l1, $l2])
        ->and(attributeColumnsFor(['parent_category' => [$l2, $r, $l1]]))->toBe([$r, $l1, $l2])
        ->and(attributeColumnsFor(['parent_category' => [$r, $unlinked->id]]))->toBe([])
        ->and(attributeColumnsFor(['parent_category' => [$l1, $unlinked->id]]))->toBe([])
        ->and(attributeColumnsFor(['parent_category' => [$l1, $legacyUnlinked->id]]))->toBe([])
        ->and(attributeColumnsFor(['parent_category' => [$legacyUnlinked->id]]))->toBe([$legacyUnlinked->id])
        ->and(attributeColumnsFor(['product_category' => [$r]]))->toBe([$r])
        ->and(attributeColumnsFor(['product_category' => [$l2, $l1]]))->toBe([$l1, $l2])
        ->and(attributeColumnsFor(['product_category' => [$l1, $r]]))->toBe([$r, $l1])
        ->and(attributeColumnsFor(['product_category' => [$l1, $unlinked->id]]))->toBe([]);
});

it('leaves out a legacy category that is no longer a root (AC-019)', function () {
    $current = ProductCategory::factory()->create();
    $legacy = categoryTabLegacyRoot($current);
    $legacy->forceFill(['parent_id' => ProductCategory::factory()->create()->id])->save();

    expect(attributeColumnsFor(['parent_category' => [$current->id]]))->toBe([$current->id])
        ->and(attributeColumnsFor(['parent_category' => [$current->id, $legacy->id]]))->toBe([]);
});

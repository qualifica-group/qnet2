<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;

// Shared by the attr.<code> category-tab grid tests (spec 0350).

if (! function_exists('categoryTabAttribute')) {
    /**
     * A category with one attribute of $type assigned in $context.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{0: ProductCategory, 1: Attribute}
     */
    function categoryTabAttribute(AttributeContext $context, string $type = 'text', array $overrides = [], ?ProductCategory $category = null): array
    {
        $category ??= ProductCategory::factory()->create();
        $attribute = $type === 'enum'
            ? Attribute::factory()->enum(2)->create($overrides)
            : Attribute::factory()->ofType($type)->create($overrides);

        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => $context->value]);

        return [$category, $attribute];
    }
}

if (! function_exists('categoryTabColumn')) {
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, mixed>|null
     */
    function categoryTabColumn(array $columns, string $id): ?array
    {
        return collect($columns)->firstWhere('id', $id);
    }
}

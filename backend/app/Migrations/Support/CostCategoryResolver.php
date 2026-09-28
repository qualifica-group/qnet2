<?php

namespace App\Migrations\Support;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;

/**
 * Provisions the "Costi" category branch of the legacy cost import (spec 0174)
 * on demand and returns the category a cost row lands in. Categories resolve
 * by name + parent (firstOrCreate), never by `old_id`: that id space belongs
 * to the legacy `service_categories`. Nothing is memoized, because every row
 * runs in its own transaction and a rolled-back row would leave a stale id.
 */
final class CostCategoryResolver
{
    public function resolve(string $source, ?string $legacyCategory): int
    {
        // Step 1: the root and the branch of this legacy table.
        $root = ProductCategory::firstOrCreate(['name' => CostProductCatalogue::ROOT, 'parent_id' => null]);
        $branch = ProductCategory::firstOrCreate(['name' => CostProductCatalogue::BRANCHES[$source], 'parent_id' => $root->id]);

        // Step 2: the branch's attributes, inherited by its sub-categories.
        $this->assignAttributes($branch, CostProductCatalogue::ATTRIBUTES[$source]);

        // Step 3: a warehouse article nests under its own legacy category.
        if ($legacyCategory === null) {
            return $branch->id;
        }

        return ProductCategory::firstOrCreate(['name' => $legacyCategory, 'parent_id' => $branch->id])->id;
    }

    /**
     * Additive, like Database\Seeders\Concerns\SeedsCategoryAttributes: an
     * attribute already in the catalogue keeps its label, and an assignment
     * made by hand is never removed.
     *
     * @param  array<string, array{code: string, name: string}>  $specs
     */
    private function assignAttributes(ProductCategory $category, array $specs): void
    {
        foreach ($specs as $spec) {
            $attribute = Attribute::firstOrCreate(
                ['code' => $spec['code']],
                ['name' => $spec['name'], 'type' => CostProductCatalogue::ATTRIBUTE_TYPE],
            );

            $isAssigned = $category->attributes()
                ->wherePivot('context', AttributeContext::Product->value)
                ->where('attributes.id', $attribute->id)
                ->exists();

            if (! $isAssigned) {
                $category->attributes()->attach($attribute->id, [
                    'context' => AttributeContext::Product->value,
                    'is_required' => false,
                    'sort_order' => 0,
                ]);
            }
        }
    }
}

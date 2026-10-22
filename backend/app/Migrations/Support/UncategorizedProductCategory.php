<?php

namespace App\Migrations\Support;

use App\Migrations\Sources\ProductsSource;
use App\Models\ProductCategory;
use RuntimeException;

/**
 * The holding category ("No categoria", seeded by QualificaCatalogSeeder) a
 * migrated product without a category of its own is filed under: a legacy
 * service without a category (user decision 2026-10-12) and the product of an
 * offer line without a service (spec 0305, D-1). Made NOT active on first use,
 * like the rest of the migrated catalogue (user directive 2026-10-13).
 */
final class UncategorizedProductCategory
{
    /**
     * @throws RuntimeException when the category has not been seeded
     */
    public function id(): int
    {
        $category = ProductCategory::query()->where('name', ProductsSource::UNCATEGORIZED_CATEGORY)->first();

        if ($category === null) {
            throw new RuntimeException("The '".ProductsSource::UNCATEGORIZED_CATEGORY."' category does not exist; run QualificaCatalogSeeder first.");
        }

        // Per-model update, so the activity log records it.
        if ($category->is_active) {
            $category->update(['is_active' => false]);
        }

        return $category->id;
    }
}

<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\ProductCategory;

if (! function_exists('projectStoreExtras')) {
    /**
     * The store payload's mandatory extras beyond the plain fields: one
     * coherent product line (category under its own business function,
     * spec 0023 REV) plus the start/end dates every project write needs.
     *
     * @return array<string, mixed>
     */
    function projectStoreExtras(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
    }
}

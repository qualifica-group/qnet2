<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;

if (! function_exists('standaloneClassificationFields')) {
    /**
     * The classification fields a standalone Campaign store needs: a
     * PipelineStatus plus one coherent product line (category under its
     * own business function, spec 0023 REV).
     *
     * @return array<string, mixed>
     */
    function standaloneClassificationFields(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'pipeline_status_id' => PipelineStatus::factory()->create()->id,
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
        ];
    }
}

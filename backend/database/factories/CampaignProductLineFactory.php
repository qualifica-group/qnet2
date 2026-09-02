<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\CampaignProductLine;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignProductLine>
 */
class CampaignProductLineFactory extends Factory
{
    protected $model = CampaignProductLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'product_category_id' => ProductCategory::factory(),
        ];
    }
}

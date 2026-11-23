<?php

namespace Database\Factories;

use App\Models\WorkOrderPage;
use App\Models\WorkOrderPagePoll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderPagePoll>
 */
class WorkOrderPagePollFactory extends Factory
{
    protected $model = WorkOrderPagePoll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_page_id' => WorkOrderPage::factory(),
            'question' => fake()->sentence(4).'?',
            'options' => [['key' => 'o1', 'label' => 'Yes'], ['key' => 'o2', 'label' => 'No']],
        ];
    }
}

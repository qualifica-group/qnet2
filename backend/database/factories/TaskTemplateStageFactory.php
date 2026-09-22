<?php

namespace Database\Factories;

use App\Models\TaskTemplate;
use App\Models\TaskTemplateStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTemplateStage>
 */
class TaskTemplateStageFactory extends Factory
{
    protected $model = TaskTemplateStage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_template_id' => TaskTemplate::factory(),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
        ];
    }

    public function forTemplate(TaskTemplate $template): static
    {
        return $this->state(fn (): array => ['task_template_id' => $template->id]);
    }

    public function atPosition(int $sortOrder): static
    {
        return $this->state(fn (): array => ['sort_order' => $sortOrder]);
    }
}

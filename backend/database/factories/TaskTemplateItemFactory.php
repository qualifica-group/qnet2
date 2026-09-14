<?php

namespace Database\Factories;

use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A minimal row: only `task_template_id` is materialised, `task_status_id`
 * stays null (D-4's own "unset" case, resolved at generation time by
 * `TaskInitialStatusResolver`) so a test opts in explicitly via
 * `inStatus()`.
 *
 * @extends Factory<TaskTemplateItem>
 */
class TaskTemplateItemFactory extends Factory
{
    protected $model = TaskTemplateItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_template_id' => TaskTemplate::factory(),
            'title' => fake()->sentence(4),
            'description' => null,
            'estimated_minutes' => null,
            'due_offset_days' => 0,
            'sort_order' => 0,
        ];
    }

    public function forTemplate(TaskTemplate $template): static
    {
        return $this->state(fn (): array => ['task_template_id' => $template->id]);
    }

    public function inStatus(TaskStatus $status): static
    {
        return $this->state(fn (): array => ['task_status_id' => $status->id]);
    }

    public function atPosition(int $sortOrder): static
    {
        return $this->state(fn (): array => ['sort_order' => $sortOrder]);
    }
}

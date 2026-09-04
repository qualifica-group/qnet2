<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * A minimal Task: only the two NOT NULL relations are materialised
     * (`task_status_id`, `creator_id`) — every optional link stays null so a
     * test opts into exactly the relations it asserts on, and the visibility
     * scope (D-9) has a known, minimal membership set to start from.
     *
     * `creator_id` is server-set in production (D-10); here the factory
     * plays that role explicitly rather than letting a test forget it, since
     * the column is NOT NULL.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => null,
            'task_status_id' => TaskStatus::factory(),
            'creator_id' => User::factory(),
            'is_blocked' => false,
            'requires_closure_feedback' => false,
        ];
    }

    public function forCreator(User $creator): static
    {
        return $this->state(fn () => ['creator_id' => $creator->id]);
    }

    public function inStatus(TaskStatus $status): static
    {
        return $this->state(fn () => ['task_status_id' => $status->id]);
    }

    public function childOf(Task $parent): static
    {
        return $this->state(fn () => ['parent_task_id' => $parent->id]);
    }

    public function requiringClosureFeedback(): static
    {
        return $this->state(fn () => ['requires_closure_feedback' => true]);
    }
}

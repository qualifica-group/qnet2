<?php

namespace Database\Factories;

use App\Enums\TaskStatusSystemKey;
use App\Models\TaskStatus;
use App\Support\BadgeTokens;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatus>
 */
class TaskStatusFactory extends Factory
{
    protected $model = TaskStatus::class;

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 0;

    /**
     * Default: a CUSTOM row — `system_key` null, therefore belonging to no
     * phase and never a closing status (D-5). The six mandatory system rows
     * are created by the migration, not built through this factory: a test
     * that needs one queries it by `system_key`, and `system_key` is not
     * mass-assignable anyway, so the `system()` state below writes it
     * directly.
     *
     * `completion_percentage` is deliberately FIXED at 0 rather than
     * randomized: it is not a cosmetic attribute — it is the value AC-020/
     * AC-022 assert on, so a random default would make percentage and
     * sort-order tests non-deterministic.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'color' => fake()->randomElement(BadgeTokens::colors()),
            'icon' => null,
            'sort_order' => self::$nextSortOrder++,
            'is_active' => true,
            'system_key' => null,
            'completion_percentage' => 0,
        ];
    }

    public function completion(int $percentage): static
    {
        return $this->state(fn () => ['completion_percentage' => $percentage]);
    }

    /**
     * Forces a system_key onto the row. `system_key` is absent from
     * #[Fillable], so a plain state() would be silently dropped by mass
     * assignment: the write has to go through forceFill, which is exactly
     * the point — only a test building a synthetic system row does this,
     * production code never can.
     */
    public function system(TaskStatusSystemKey $key): static
    {
        return $this
            ->afterMaking(fn (TaskStatus $status) => $status->forceFill(['system_key' => $key->value]))
            ->afterCreating(fn (TaskStatus $status) => $status->forceFill(['system_key' => $key->value])->save());
    }
}

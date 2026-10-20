<?php

namespace Database\Factories;

use App\Enums\StaffTrainingOutcome;
use App\Models\StaffCourse;
use App\Models\StaffTraining;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTraining>
 */
class StaffTrainingFactory extends Factory
{
    protected $model = StaffTraining::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'staff_course_id' => StaffCourse::factory(),
            'completed_on' => fake()->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
            'expires_on' => null,
            'outcome' => StaffTrainingOutcome::Passed,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['outcome' => StaffTrainingOutcome::Failed]);
    }
}

<?php

namespace Database\Factories;

use App\Models\StaffCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffCourse>
 */
class StaffCourseFactory extends Factory
{
    protected $model = StaffCourse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'duration_minutes' => null,
            'validity_months' => null,
            'is_active' => true,
        ];
    }

    public function validFor(int $months): static
    {
        return $this->state(fn () => ['validity_months' => $months]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

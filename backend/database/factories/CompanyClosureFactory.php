<?php

namespace Database\Factories;

use App\Enums\CompanyClosureKind;
use App\Models\AbsenceType;
use App\Models\CompanyClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyClosure>
 */
class CompanyClosureFactory extends Factory
{
    protected $model = CompanyClosure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $day = fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d');

        return [
            'name' => fake()->unique()->words(2, true),
            'starts_on' => $day,
            'ends_on' => $day,
            'kind' => CompanyClosureKind::Holiday,
            'absence_type_id' => null,
            'applies_to_all_sites' => true,
            'repeats_yearly' => false,
        ];
    }

    public function collectiveLeave(?AbsenceType $type = null): static
    {
        return $this->state(fn () => [
            'kind' => CompanyClosureKind::CollectiveLeave,
            'absence_type_id' => $type?->id ?? AbsenceType::factory(),
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['repeats_yearly' => true]);
    }
}

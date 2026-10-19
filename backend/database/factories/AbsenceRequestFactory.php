<?php

namespace Database\Factories;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceRequest>
 */
class AbsenceRequestFactory extends Factory
{
    protected $model = AbsenceRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d');

        return [
            'user_id' => User::factory(),
            'absence_type_id' => AbsenceType::factory(),
            'status' => AbsenceRequestStatus::Draft,
            'is_full_day' => true,
            'starts_on' => $start,
            'ends_on' => $start,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function submitted(int $minutes = 480): static
    {
        return $this->state(fn () => [
            'status' => AbsenceRequestStatus::Submitted,
            'minutes' => $minutes,
            'submitted_at' => now(),
        ]);
    }

    public function approved(int $minutes = 480): static
    {
        return $this->state(fn () => [
            'status' => AbsenceRequestStatus::Approved,
            'minutes' => $minutes,
            'submitted_at' => now(),
            'decided_at' => now(),
            'decided_by' => User::factory(),
        ]);
    }
}

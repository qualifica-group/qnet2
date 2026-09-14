<?php

namespace Database\Factories;

use App\Models\TimeEntryDayNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntryDayNote>
 */
class TimeEntryDayNoteFactory extends Factory
{
    protected $model = TimeEntryDayNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'note' => fake()->sentence(8),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn () => ['date' => $date]);
    }
}

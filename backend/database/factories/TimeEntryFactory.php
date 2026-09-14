<?php

namespace Database\Factories;

use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    /**
     * A minimal segnatempo: date-only duration (no `start_time`/`end_time`),
     * every optional record link null — a test opts into exactly the links
     * it asserts on. `minutes` is drawn from the 1..1440 domain (D-6).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'title' => fake()->sentence(4),
            'task_type_id' => TaskType::factory(),
            'start_time' => null,
            'end_time' => null,
            'minutes' => fake()->randomElement([15, 30, 45, 60, 90, 120, 240]),
            'notes' => null,
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

    /**
     * An interval-backed segnatempo: `minutes` is derived from the interval
     * itself, the way the client pre-fills it (D-6) — a test asserting on
     * `start_time`/`end_time` never has to keep `minutes` in sync by hand.
     */
    public function withInterval(string $startTime, string $endTime): static
    {
        $minutes = (strtotime($endTime) - strtotime($startTime)) / 60;

        return $this->state(fn () => [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'minutes' => $minutes,
        ]);
    }
}

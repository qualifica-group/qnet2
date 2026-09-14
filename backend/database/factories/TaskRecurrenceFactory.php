<?php

namespace Database\Factories;

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Models\TaskRecurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskRecurrence>
 */
class TaskRecurrenceFactory extends Factory
{
    protected $model = TaskRecurrence::class;

    /**
     * A minimal, never-ending daily rule — the neutral default a test
     * overrides for whichever frequency/end condition it actually exercises.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'frequency' => TaskRecurrenceFrequency::Daily,
            'interval' => 1,
            'weekdays' => null,
            'month_day' => null,
            'ends' => TaskRecurrenceEnd::Never,
            'ends_on' => null,
            'occurrence_count' => null,
            'generated_until' => null,
        ];
    }

    public function weekly(array $weekdays, int $interval = 1): static
    {
        return $this->state(fn () => [
            'frequency' => TaskRecurrenceFrequency::Weekly,
            'interval' => $interval,
            'weekdays' => $weekdays,
        ]);
    }

    public function monthly(int $monthDay, int $interval = 1): static
    {
        return $this->state(fn () => [
            'frequency' => TaskRecurrenceFrequency::Monthly,
            'interval' => $interval,
            'month_day' => $monthDay,
        ]);
    }

    public function endingOn(string $date): static
    {
        return $this->state(fn () => ['ends' => TaskRecurrenceEnd::OnDate, 'ends_on' => $date]);
    }

    public function endingAfter(int $occurrenceCount): static
    {
        return $this->state(fn () => ['ends' => TaskRecurrenceEnd::AfterCount, 'occurrence_count' => $occurrenceCount]);
    }
}

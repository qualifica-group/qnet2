<?php

namespace Database\Factories;

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
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
            'month_mode' => null,
            'ordinal' => null,
            'ordinal_weekday' => null,
            'year_month' => null,
            'workdays_only' => false,
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
            'month_mode' => TaskRecurrenceMonthMode::Fixed,
            'month_day' => $monthDay,
        ]);
    }

    /** Spec 0155, D-1: "the Nth <weekday> of the month", e.g. the 2nd Tuesday. */
    public function monthlyOrdinal(int $ordinal, int $ordinalWeekday, int $interval = 1): static
    {
        return $this->state(fn () => [
            'frequency' => TaskRecurrenceFrequency::Monthly,
            'interval' => $interval,
            'month_mode' => TaskRecurrenceMonthMode::Ordinal,
            'ordinal' => $ordinal,
            'ordinal_weekday' => $ordinalWeekday,
        ]);
    }

    public function yearly(int $yearMonth, int $monthDay, int $interval = 1): static
    {
        return $this->state(fn () => [
            'frequency' => TaskRecurrenceFrequency::Yearly,
            'interval' => $interval,
            'month_mode' => TaskRecurrenceMonthMode::Fixed,
            'year_month' => $yearMonth,
            'month_day' => $monthDay,
        ]);
    }

    /** Spec 0155, D-1: "the Nth <weekday> of <month>", e.g. the 2nd Tuesday of March. */
    public function yearlyOrdinal(int $yearMonth, int $ordinal, int $ordinalWeekday, int $interval = 1): static
    {
        return $this->state(fn () => [
            'frequency' => TaskRecurrenceFrequency::Yearly,
            'interval' => $interval,
            'month_mode' => TaskRecurrenceMonthMode::Ordinal,
            'year_month' => $yearMonth,
            'ordinal' => $ordinal,
            'ordinal_weekday' => $ordinalWeekday,
        ]);
    }

    /** Spec 0155, D-1: "every N days", q-net's own alias for daily. */
    public function custom(int $interval): static
    {
        return $this->state(fn () => [
            'frequency' => TaskRecurrenceFrequency::Custom,
            'interval' => $interval,
        ]);
    }

    public function workdaysOnly(): static
    {
        return $this->state(fn () => ['workdays_only' => true]);
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

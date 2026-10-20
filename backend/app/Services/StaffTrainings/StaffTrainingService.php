<?php

namespace App\Services\StaffTrainings;

use App\Models\StaffCourse;
use App\Models\StaffTraining;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Writes of the staff training register (spec 0284 D-3).
 */
class StaffTrainingService
{
    /** Relations read by StaffTrainingResource. */
    public const array RELATIONS = ['user:id,name', 'course:id,name,validity_months', 'recordedBy:id,name', 'attachments'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, array $attributes): StaffTraining
    {
        // Step 1: the expiry is a snapshot of the course validity at save time
        $attributes['expires_on'] = $this->expiryFor((int) $attributes['staff_course_id'], (string) $attributes['completed_on']);

        // Step 2: persist, remembering who recorded it
        $training = StaffTraining::create([...$attributes, 'recorded_by' => $actor->id]);

        return $this->detail($training);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(StaffTraining $training, array $attributes): StaffTraining
    {
        // Step 1: recompute the expiry only when the course or the date really change
        if ($this->changesExpiry($training, $attributes)) {
            $attributes['expires_on'] = $this->expiryFor(
                (int) ($attributes['staff_course_id'] ?? $training->staff_course_id),
                (string) ($attributes['completed_on'] ?? $training->completed_on->format('Y-m-d')),
            );
        }

        // Step 2: persist
        $training->fill($attributes)->save();

        return $this->detail($training);
    }

    public function detail(StaffTraining $training): StaffTraining
    {
        return $training->unsetRelations()->load(self::RELATIONS);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function changesExpiry(StaffTraining $training, array $attributes): bool
    {
        return (isset($attributes['staff_course_id']) && (int) $attributes['staff_course_id'] !== (int) $training->staff_course_id)
            || (isset($attributes['completed_on']) && $attributes['completed_on'] !== $training->completed_on->format('Y-m-d'));
    }

    private function expiryFor(int $courseId, string $completedOn): ?string
    {
        $months = StaffCourse::query()->whereKey($courseId)->value('validity_months');

        return $months === null
            ? null
            : CarbonImmutable::parse($completedOn)->addMonthsNoOverflow((int) $months)->format('Y-m-d');
    }
}

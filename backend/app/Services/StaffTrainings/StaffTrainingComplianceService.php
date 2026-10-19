<?php

declare(strict_types=1);

namespace App\Services\StaffTrainings;

use App\Enums\StaffTrainingOutcome;
use App\Enums\StaffTrainingStatus;
use App\Models\StaffCourse;
use App\Models\StaffTraining;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The "Situazione" view (spec 0284 D-4): person x course with the standing of
 * the latest passed training. Four queries whatever the number of people.
 */
final class StaffTrainingComplianceService
{
    public function __construct(private readonly StaffTrainingVisibility $visibility) {}

    /**
     * @return array{expiring_days: int, rows: list<array<string, mixed>>}
     */
    public function build(User $actor, ?StaffTrainingStatus $status, ?int $businessFunctionId, ?int $courseId): array
    {
        // Step 1: the active people the actor sees
        $people = $this->people($actor, $businessFunctionId);
        $personIds = $people->modelKeys();

        // Step 2: the courses each one needs, and the latest passed training per course
        $required = $this->requiredCourseIds($personIds, $courseId);
        $latest = $this->latestPassed($personIds, $courseId);
        $courses = StaffCourse::query()->whereKey($this->courseIds($required, $latest))->get(['id', 'name', 'is_active'])->keyBy('id');

        // Step 3: one row per (person, course) that is required-and-active or was passed
        $today = CarbonImmutable::today();
        $rows = [];

        foreach ($people as $person) {
            foreach ($courses as $course) {
                $training = $latest[$person->id][$course->id] ?? null;
                $isRequired = isset($required[$person->id][$course->id]);

                if ($training === null && ! ($isRequired && $course->is_active)) {
                    continue;
                }

                $rowStatus = $this->statusOf($training, $today);

                if ($status === null || $rowStatus === $status) {
                    $rows[] = $this->row($person, $course, $isRequired, $rowStatus, $training);
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['user']['name'], $a['course']['name'], $a['user']['id'], $a['course']['id']]
            <=> [$b['user']['name'], $b['course']['name'], $b['user']['id'], $b['course']['id']]);

        return ['expiring_days' => (int) config('staff_training.expiring_days'), 'rows' => $rows];
    }

    /**
     * @return Collection<int, User>
     */
    private function people(User $actor, ?int $businessFunctionId): Collection
    {
        $visible = $this->visibility->visibleUserIds($actor);

        return User::query()
            ->select(['id', 'name'])
            ->where('is_active', true)
            ->when($visible !== null, static fn (Builder $query) => $query->whereKey($visible))
            ->when($businessFunctionId !== null, static fn (Builder $query) => $query->whereHas(
                'businessFunctions',
                static fn (Builder $functions) => $functions->whereKey($businessFunctionId),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * The courses required by the job roles of each person.
     *
     * @param  list<int>  $personIds
     * @return array<int, array<int, true>> user id => course id => true
     */
    private function requiredCourseIds(array $personIds, ?int $courseId): array
    {
        $links = DB::table('employment_profiles')
            ->join('employment_profile_job_role', 'employment_profile_job_role.employment_profile_id', '=', 'employment_profiles.id')
            ->join('job_role_staff_course', 'job_role_staff_course.job_role_id', '=', 'employment_profile_job_role.job_role_id')
            ->whereIn('employment_profiles.user_id', $personIds)
            ->when($courseId !== null, static fn ($query) => $query->where('job_role_staff_course.staff_course_id', $courseId))
            ->distinct()
            ->get(['employment_profiles.user_id', 'job_role_staff_course.staff_course_id']);

        $required = [];

        foreach ($links as $link) {
            $required[(int) $link->user_id][(int) $link->staff_course_id] = true;
        }

        return $required;
    }

    /**
     * The most recent passed training of each person for each course.
     *
     * @param  list<int>  $personIds
     * @return array<int, array<int, StaffTraining>> user id => course id => training
     */
    private function latestPassed(array $personIds, ?int $courseId): array
    {
        $trainings = StaffTraining::query()
            ->whereIn('user_id', $personIds)
            ->where('outcome', StaffTrainingOutcome::Passed)
            ->when($courseId !== null, static fn (Builder $query) => $query->where('staff_course_id', $courseId))
            ->orderByDesc('completed_on')
            ->orderByDesc('id')
            ->get();

        $latest = [];

        foreach ($trainings as $training) {
            $latest[$training->user_id][$training->staff_course_id] ??= $training;
        }

        return $latest;
    }

    /**
     * @param  array<int, array<int, true>>  $required
     * @param  array<int, array<int, StaffTraining>>  $latest
     * @return list<int>
     */
    private function courseIds(array $required, array $latest): array
    {
        $ids = [];

        foreach ([...array_values($required), ...array_values($latest)] as $byCourse) {
            foreach (array_keys($byCourse) as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    private function statusOf(?StaffTraining $training, CarbonImmutable $today): StaffTrainingStatus
    {
        if ($training === null) {
            return StaffTrainingStatus::Missing;
        }

        if ($training->expires_on === null) {
            return StaffTrainingStatus::Valid;
        }

        $expiresOn = CarbonImmutable::parse($training->expires_on->format('Y-m-d'));

        return match (true) {
            $expiresOn->lt($today) => StaffTrainingStatus::Expired,
            $expiresOn->lte($today->addDays((int) config('staff_training.expiring_days'))) => StaffTrainingStatus::Expiring,
            default => StaffTrainingStatus::Valid,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $person, StaffCourse $course, bool $required, StaffTrainingStatus $status, ?StaffTraining $training): array
    {
        return [
            'user' => ['id' => $person->id, 'name' => $person->name],
            'course' => ['id' => $course->id, 'name' => $course->name],
            'required' => $required,
            'status' => $status->value,
            'completed_on' => $training?->completed_on->format('Y-m-d'),
            'expires_on' => $training?->expires_on?->format('Y-m-d'),
            'training_id' => $training?->id,
        ];
    }
}

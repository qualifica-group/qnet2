<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\StaffCourses\StaffCourseData;
use App\Models\StaffCourse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the staff courses catalogue (spec 0284 D-2).
 */
class StaffCourseService
{
    public function create(StaffCourseData $data): StaffCourse
    {
        return DB::transaction(function () use ($data): StaffCourse {
            $course = StaffCourse::create($data->attributes());
            $this->syncJobRoles($course, $data);

            return $this->withRelations($course->refresh());
        });
    }

    public function update(StaffCourse $course, StaffCourseData $data): StaffCourse
    {
        return DB::transaction(function () use ($course, $data): StaffCourse {
            $course->fill($data->attributes())->save();
            $this->syncJobRoles($course, $data);

            return $this->withRelations($course);
        });
    }

    /**
     * Restrictive delete (spec 0284 D-2): a course used by a training is
     * deactivated, never removed.
     */
    public function delete(StaffCourse $course): void
    {
        if ($course->trainings()->exists()) {
            abort(422, __('staff_courses.in_use'));
        }

        $course->delete();
    }

    /**
     * Loads what StaffCourseResource reads, so it never lazy-loads.
     */
    public function withRelations(StaffCourse $course): StaffCourse
    {
        return $course->load('jobRoles:id,name')->loadCount('trainings');
    }

    /**
     * Active courses by name; the requested `ids` (edit-mode hydration) are
     * served even when inactive and do not inflate the total.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = StaffCourse::query()->select(['id', 'name'])->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $result = $query->page($base, fn (Builder $ordered) => $ordered->orderBy('name')->orderBy('id'));

        if (! $query->hasIds()) {
            return $result;
        }

        $missingIds = array_values(array_diff($query->ids, $result->items->pluck('id')->all()));
        $hydrated = StaffCourse::query()->select(['id', 'name'])->whereIn('id', $missingIds)->orderBy('name')->orderBy('id')->get();

        return $result->withItems($result->items->concat($hydrated));
    }

    private function syncJobRoles(StaffCourse $course, StaffCourseData $data): void
    {
        if ($data->jobRoleIds() !== null) {
            $course->jobRoles()->sync($data->jobRoleIds());
        }
    }
}

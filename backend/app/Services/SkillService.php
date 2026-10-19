<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Skills\SkillData;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the skills catalogue (spec 0286 D-2).
 */
class SkillService
{
    public function create(SkillData $data): Skill
    {
        return DB::transaction(function () use ($data): Skill {
            $skill = Skill::create($data->attributes());
            $this->syncJobRoles($skill, $data);

            return $this->withRelations($skill->refresh());
        });
    }

    public function update(Skill $skill, SkillData $data): Skill
    {
        return DB::transaction(function () use ($skill, $data): Skill {
            $skill->fill($data->attributes())->save();
            $this->syncJobRoles($skill, $data);

            return $this->withRelations($skill);
        });
    }

    /**
     * Restrictive delete (spec 0286 D-2): a skill assigned to a person is
     * deactivated, never removed.
     */
    public function delete(Skill $skill): void
    {
        if ($skill->userSkills()->exists()) {
            abort(422, __('skills.in_use'));
        }

        $skill->delete();
    }

    /**
     * Loads what SkillResource reads, so it never lazy-loads.
     */
    public function withRelations(Skill $skill): Skill
    {
        return $skill->unsetRelation('jobRoles')->load('jobRoles:id,name')->loadCount('userSkills');
    }

    /**
     * Active skills by name; the requested `ids` (edit-mode hydration) are
     * served even when inactive and do not inflate the total.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Skill::query()->select(['id', 'name', 'category'])->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $result = $query->page($base, fn (Builder $ordered) => $ordered->orderBy('name')->orderBy('id'));

        if (! $query->hasIds()) {
            return $result;
        }

        $missingIds = array_values(array_diff($query->ids, $result->items->pluck('id')->all()));
        $hydrated = Skill::query()->select(['id', 'name', 'category'])->whereIn('id', $missingIds)->orderBy('name')->orderBy('id')->get();

        return $result->withItems($result->items->concat($hydrated));
    }

    private function syncJobRoles(Skill $skill, SkillData $data): void
    {
        if ($data->jobRoleSync() !== null) {
            $skill->jobRoles()->sync($data->jobRoleSync());
        }
    }
}

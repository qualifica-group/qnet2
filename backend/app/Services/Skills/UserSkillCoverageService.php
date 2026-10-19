<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Enums\SkillCoverageStatus;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The "Copertura" view (spec 0286 D-6): person x required skill, with the
 * highest minimum level among the person's job roles. Four queries whatever
 * the number of people.
 */
final class UserSkillCoverageService
{
    public function __construct(private readonly UserSkillVisibility $visibility) {}

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function build(User $actor, ?SkillCoverageStatus $status, ?int $businessFunctionId, ?int $skillId): array
    {
        // Step 1: the active people the actor sees
        $people = $this->people($actor, $businessFunctionId);
        $personIds = $people->modelKeys();

        // Step 2: the active skills each one needs, and what they have
        $required = $this->requiredLevels($personIds, $skillId);
        $held = UserSkill::query()->whereIn('user_id', $personIds)->whereIn('skill_id', $this->skillIds($required))->get()
            ->groupBy('user_id')->map(fn (Collection $rows) => $rows->keyBy('skill_id'));
        $skills = Skill::query()->whereKey($this->skillIds($required))->get(['id', 'name', 'category'])->keyBy('id');

        // Step 3: one row per (person, required skill)
        $rows = [];

        foreach ($people as $person) {
            foreach ($required[$person->id] ?? [] as $id => $requiredLevel) {
                $userSkill = $held[$person->id][$id] ?? null;
                $rowStatus = $this->statusOf($userSkill, $requiredLevel);

                if ($status === null || $rowStatus === $status) {
                    $rows[] = $this->row($person, $skills[$id], $requiredLevel, $rowStatus, $userSkill);
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['user']['name'], $a['skill']['name'], $a['user']['id'], $a['skill']['id']]
            <=> [$b['user']['name'], $b['skill']['name'], $b['user']['id'], $b['skill']['id']]);

        return ['rows' => $rows];
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
     * The highest minimum level among the job roles of each person, per active skill.
     *
     * @param  list<int>  $personIds
     * @return array<int, array<int, int>> user id => skill id => required level
     */
    private function requiredLevels(array $personIds, ?int $skillId): array
    {
        $links = DB::table('employment_profiles')
            ->join('employment_profile_job_role', 'employment_profile_job_role.employment_profile_id', '=', 'employment_profiles.id')
            ->join('job_role_skill', 'job_role_skill.job_role_id', '=', 'employment_profile_job_role.job_role_id')
            ->join('skills', 'skills.id', '=', 'job_role_skill.skill_id')
            ->where('skills.is_active', true)
            ->whereIn('employment_profiles.user_id', $personIds)
            ->when($skillId !== null, static fn ($query) => $query->where('job_role_skill.skill_id', $skillId))
            ->groupBy('employment_profiles.user_id', 'job_role_skill.skill_id')
            ->get(['employment_profiles.user_id', 'job_role_skill.skill_id', DB::raw('MAX(job_role_skill.min_level) as required_level')]);

        $required = [];

        foreach ($links as $link) {
            $required[(int) $link->user_id][(int) $link->skill_id] = (int) $link->required_level;
        }

        return $required;
    }

    /**
     * @param  array<int, array<int, int>>  $required
     * @return list<int>
     */
    private function skillIds(array $required): array
    {
        $ids = [];

        foreach ($required as $bySkill) {
            foreach (array_keys($bySkill) as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    private function statusOf(?UserSkill $userSkill, int $requiredLevel): SkillCoverageStatus
    {
        return match (true) {
            $userSkill === null => SkillCoverageStatus::Missing,
            $userSkill->level->value >= $requiredLevel => SkillCoverageStatus::Covered,
            default => SkillCoverageStatus::Below,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $person, Skill $skill, int $requiredLevel, SkillCoverageStatus $status, ?UserSkill $userSkill): array
    {
        return [
            'user' => ['id' => $person->id, 'name' => $person->name],
            'skill' => ['id' => $skill->id, 'name' => $skill->name, 'category' => $skill->category],
            'required_level' => $requiredLevel,
            'level' => $userSkill?->level->value,
            'status' => $status->value,
            'user_skill_id' => $userSkill?->id,
        ];
    }
}

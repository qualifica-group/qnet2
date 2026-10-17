<?php

declare(strict_types=1);

namespace App\Services\OrgChart;

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the org chart (spec 0273 D-2/D-3): both views are built from a
 * FIXED number of batched queries (users + their employment and avatar, job
 * roles, reporting pivot, function links), never one per person, so ~200
 * people cost the same handful of queries as 5.
 *
 * Only active, non-service users take part. A person belongs to a function
 * when at least one of their competence rows (`employment_product_lines`)
 * sits on it; `covers_all_product_categories` people have no rows and go in
 * the separate "all functions" list.
 */
final class OrgChartReadService
{
    /**
     * @return array{functions: list<array<string, mixed>>, all_functions_people: list<array<string, mixed>>}
     */
    public function functions(): array
    {
        // Step 1: the people and their per-profile batches.
        $users = $this->activeUsers();
        $profileIds = $this->profileIds($users);
        $jobRoles = $this->jobRolesByProfile($profileIds);

        // Step 2: the function ids each profile belongs to.
        $functionIdsByProfile = [];
        foreach ($this->functionLinks($profileIds) as $link) {
            $functionIdsByProfile[$link->employment_profile_id][] = $link->business_function_id;
        }

        // Step 3: people per function; iterating the name-sorted users keeps
        // every function's list in name order.
        $personByProfile = [];
        $peopleByFunction = [];
        foreach ($users as $user) {
            if ($user->employment === null) {
                continue;
            }

            $personByProfile[$user->employment->id] = $person = $this->person($user, $jobRoles);

            foreach ($functionIdsByProfile[$user->employment->id] ?? [] as $functionId) {
                $peopleByFunction[$functionId][] = $person;
            }
        }

        return [
            'functions' => $this->functionRows($peopleByFunction),
            'all_functions_people' => $users
                ->filter(fn (User $user): bool => (bool) $user->employment?->covers_all_product_categories)
                ->map(fn (User $user): array => $personByProfile[$user->employment->id])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function people(): array
    {
        $users = $this->activeUsers();
        $profileIds = $this->profileIds($users);
        $jobRoles = $this->jobRolesByProfile($profileIds);
        $managersByProfile = $this->managerIdsByProfile($profileIds);
        $functionNames = $this->functionNamesByProfile($profileIds);
        $listed = array_flip($users->pluck('id')->all());

        return $users->map(function (User $user) use ($jobRoles, $managersByProfile, $functionNames, $listed): array {
            $profileId = $user->employment?->id;
            $managerIds = array_values(array_filter(
                $managersByProfile[$profileId] ?? [],
                static fn (int $id): bool => isset($listed[$id]),
            ));
            sort($managerIds);

            return $this->person($user, $jobRoles) + [
                'manager_ids' => $managerIds,
                'business_functions' => $functionNames[$profileId] ?? [],
            ];
        })->values()->all();
    }

    /**
     * One person with ALL their reporting lines (move response, not filtered
     * to the listed people).
     *
     * @return array<string, mixed>
     */
    public function personWithManagers(User $user): array
    {
        $user->load(['avatar', 'employment']);
        $profileId = $user->employment?->id;
        $managerIds = $profileId === null ? [] : ($this->managerIdsByProfile([$profileId])[$profileId] ?? []);
        sort($managerIds);

        return $this->person($user, $this->jobRolesByProfile($profileId === null ? [] : [$profileId])) + ['manager_ids' => $managerIds];
    }

    /**
     * @return Collection<int, User>
     */
    private function activeUsers(): Collection
    {
        return User::query()
            ->excludingServiceAccounts()
            ->where('is_active', true)
            ->with(['avatar', 'employment:id,user_id,is_manager,covers_all_product_categories'])
            ->orderBy('name')
            ->orderBy('id')
            ->get(['users.id', 'users.name']);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function profileIds(Collection $users): array
    {
        return $users->map(fn (User $user): ?int => $user->employment?->id)->filter()->values()->all();
    }

    /**
     * @param  array<int, list<string>>  $jobRolesByProfile
     * @return array{id: int, name: string, avatar_url: string|null, job_roles: list<string>, is_manager: bool}
     */
    private function person(User $user, array $jobRolesByProfile): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
            'job_roles' => $jobRolesByProfile[$user->employment?->id] ?? [],
            'is_manager' => (bool) $user->employment?->is_manager,
        ];
    }

    /**
     * @param  list<int>  $profileIds
     * @return array<int, list<string>> job role names, alphabetical, by profile id
     */
    private function jobRolesByProfile(array $profileIds): array
    {
        $names = [];

        $rows = DB::table('employment_profile_job_role')
            ->join('job_roles', 'job_roles.id', '=', 'employment_profile_job_role.job_role_id')
            ->whereIn('employment_profile_job_role.employment_profile_id', $profileIds)
            ->orderBy('job_roles.name')
            ->get(['employment_profile_job_role.employment_profile_id as profile_id', 'job_roles.name']);

        foreach ($rows as $row) {
            $names[$row->profile_id][] = $row->name;
        }

        return $names;
    }

    /**
     * @param  list<int>  $profileIds
     * @return array<int, list<int>>
     */
    private function managerIdsByProfile(array $profileIds): array
    {
        $ids = [];

        $rows = DB::table('employment_profile_manager')
            ->whereIn('employment_profile_id', $profileIds)
            ->get(['employment_profile_id', 'user_id']);

        foreach ($rows as $row) {
            $ids[$row->employment_profile_id][] = (int) $row->user_id;
        }

        return $ids;
    }

    /**
     * Distinct (profile, function) links, ordered by function name.
     *
     * @param  list<int>  $profileIds
     * @return Collection<int, object{employment_profile_id: int, business_function_id: int, name: string}>
     */
    private function functionLinks(array $profileIds): Collection
    {
        return DB::table('employment_product_lines')
            ->join('business_functions', 'business_functions.id', '=', 'employment_product_lines.business_function_id')
            ->whereIn('employment_product_lines.employment_profile_id', $profileIds)
            ->distinct()
            ->orderBy('business_functions.name')
            ->get(['employment_product_lines.employment_profile_id', 'employment_product_lines.business_function_id', 'business_functions.name']);
    }

    /**
     * @param  list<int>  $profileIds
     * @return array<int, list<string>>
     */
    private function functionNamesByProfile(array $profileIds): array
    {
        $names = [];

        foreach ($this->functionLinks($profileIds) as $link) {
            $names[$link->employment_profile_id][] = $link->name;
        }

        return $names;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $peopleByFunction
     * @return list<array<string, mixed>>
     */
    private function functionRows(array $peopleByFunction): array
    {
        return BusinessFunction::query()
            ->leftJoin('users as managers', 'managers.id', '=', 'business_functions.manager_id')
            ->orderBy('business_functions.name')
            ->get([
                'business_functions.id', 'business_functions.name', 'business_functions.parent_id',
                'business_functions.manager_id', 'business_functions.is_business_unit',
                'business_functions.is_business_service', 'managers.name as manager_name',
            ])
            ->map(fn (BusinessFunction $function): array => [
                'id' => $function->id,
                'name' => $function->name,
                'type' => $function->is_business_unit ? 'business_unit' : ($function->is_business_service ? 'business_service' : null),
                'parent_id' => $function->parent_id,
                'manager' => $function->manager_id === null ? null : ['id' => $function->manager_id, 'name' => $function->manager_name],
                'people' => $peopleByFunction[$function->id] ?? [],
            ])
            ->all();
    }
}

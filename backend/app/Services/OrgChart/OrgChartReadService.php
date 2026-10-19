<?php

declare(strict_types=1);

namespace App\Services\OrgChart;

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the org chart (spec 0273 D-2/D-3): both views are built from a
 * FIXED number of batched queries (users + their employment and avatar, job
 * roles, reporting pivot, function links), never one per person, so ~200
 * people cost the same handful of queries as 5.
 *
 * Only active, non-service users take part. A person belongs to a function
 * through the membership pivot `business_function_user` (spec 0275 D-7);
 * people with no row there go in the separate "unassigned" list.
 */
final class OrgChartReadService
{
    /** Person.is_manager (spec 0276 D-6): the person manages at least one function. */
    private const string MANAGES_FUNCTIONS = 'managedBusinessFunctions as manages_functions';

    /**
     * @return array{functions: list<array<string, mixed>>, unassigned_people: list<array<string, mixed>>}
     */
    public function functions(): array
    {
        // Step 1: the people and their per-profile batches.
        $users = $this->activeUsers();
        $profileIds = $this->profileIds($users);
        $jobRoles = $this->jobRolesByProfile($profileIds);

        // Step 2: the function ids each user belongs to.
        $functionIdsByUser = [];
        foreach ($this->functionLinks($users->pluck('id')->all()) as $link) {
            $functionIdsByUser[$link->user_id][] = $link->business_function_id;
        }

        // Step 3: people per function; iterating the name-sorted users keeps
        // every function's list in name order.
        $peopleByFunction = [];
        $unassigned = [];
        foreach ($users as $user) {
            $person = $this->person($user, $jobRoles);

            foreach ($functionIdsByUser[$user->id] ?? [] as $functionId) {
                $peopleByFunction[$functionId][] = $person;
            }

            if (! isset($functionIdsByUser[$user->id])) {
                $unassigned[] = $person;
            }
        }

        return [
            'functions' => $this->functionRows($peopleByFunction),
            'unassigned_people' => $unassigned,
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
        $functionNames = $this->functionsByUser($users->pluck('id')->all());
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
                'business_functions' => $functionNames[$user->id] ?? [],
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
        $user->load(['avatar', 'employment'])->loadExists(self::MANAGES_FUNCTIONS);
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
            ->with(['avatar', 'employment:id,user_id'])
            ->withExists(self::MANAGES_FUNCTIONS)
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
            'is_manager' => (bool) $user->manages_functions,
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
     * Membership links (user, function), ordered by function name.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, object{user_id: int, business_function_id: int, name: string, manager_id: int|null}>
     */
    private function functionLinks(array $userIds): Collection
    {
        return DB::table('business_function_user')
            ->join('business_functions', 'business_functions.id', '=', 'business_function_user.business_function_id')
            ->whereIn('business_function_user.user_id', $userIds)
            ->orderBy('business_functions.name')
            ->get(['business_function_user.user_id', 'business_function_user.business_function_id', 'business_functions.name', 'business_functions.manager_id']);
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, list<array{id: int, name: string, is_manager: bool}>> by user id, name-ordered
     */
    private function functionsByUser(array $userIds): array
    {
        $functions = [];

        foreach ($this->functionLinks($userIds) as $link) {
            $functions[$link->user_id][] = [
                'id' => (int) $link->business_function_id,
                'name' => $link->name,
                'is_manager' => (int) $link->manager_id === (int) $link->user_id,
            ];
        }

        return $functions;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $peopleByFunction
     * @return list<array<string, mixed>>
     */
    private function functionRows(array $peopleByFunction): array
    {
        return BusinessFunction::query()
            // Only an active, non-service manager is shown: the chart lists active people only.
            ->leftJoin('users as managers', static function (JoinClause $join): void {
                $join->on('managers.id', '=', 'business_functions.manager_id')
                    ->where('managers.is_active', true)
                    ->where('managers.is_service_account', false);
            })
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
                'manager' => $function->manager_name === null ? null : ['id' => $function->manager_id, 'name' => $function->manager_name],
                'people' => $peopleByFunction[$function->id] ?? [],
            ])
            ->all();
    }
}

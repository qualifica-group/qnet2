<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Users\CreateUserData;
use App\DataObjects\Users\EmploymentData;
use App\DataObjects\Users\ProfileData;
use App\DataObjects\Users\UpdateUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserService
{
    /**
     * Relations eager-loaded on the user returned by create()/update() so the
     * UserResource can emit the nested personal-data and employment trees
     * (with their {id,label} references) without a second request.
     *
     * @var array<int, string>
     */
    private const array WRITE_RESULT_RELATIONS = [
        'personalData.contacts',
        'personalData.addresses',
        // The comuni of birth and residence, so PersonalDataResource can emit
        // their names.
        'personalData.birthCity',
        'personalData.residenceCity',
        'employment.reportsTo',
        'employment.businessFunction',
        'employment.company',
        // The operational-site label is "line1[- city]" (EmploymentResource),
        // so the pivot's sites, their primary address and city must all be
        // eager-loaded, or reading them would lazy-load (blocked outside
        // production). One relation covers both the physical and every
        // remote site (spec 0103): EmploymentResource tells them apart via
        // the pivot's `is_primary` flag, not via separate relations.
        'employment.operationalSites.addresses.city',
    ];

    public function __construct(
        private readonly RoleAssignmentGuard $guard,
        private readonly ProfileWriter $profileWriter,
        private readonly EmploymentWriter $employmentWriter,
    ) {}

    /**
     * Eager-load the nested read tree (personal-data + employment, with their
     * {id,label} references) the UserResource emits only `whenLoaded`, so a
     * plain GET /users/{user} returns the SAME shape create()/update() do.
     * Single source of truth for the relation set (WRITE_RESULT_RELATIONS).
     */
    public function loadProfileTree(User $user): User
    {
        return $user->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * The privileged role that grants every ability via Gate::before. Kept as a
     * back-compatible alias of the guard's constant: existing callers
     * (RoleService, RolesTableDefinition, tests) reference UserService::PRIVILEGED_ROLE.
     */
    public const string PRIVILEGED_ROLE = RoleAssignmentGuard::PRIVILEGED_ROLE;

    /**
     * Role names the given actor is allowed to assign. Delegates to the shared
     * RoleAssignmentGuard (single source of truth) — kept public because the user
     * FormRequests and UsersTableDefinition call it.
     *
     * @return array<int, string>
     */
    public function assignableRoleNames(User $actor): array
    {
        return $this->guard->assignableRoleNames($actor);
    }

    /**
     * Assignable roles as an id => name map (same authority as
     * assignableRoleNames). The user FormRequests call it to validate submitted
     * role IDS and resolve them to the names the service/guard operate on.
     *
     * @return array<int, string>
     */
    public function assignableRoleMap(User $actor): array
    {
        return $this->guard->assignableRoleMap($actor);
    }

    /**
     * Create a new user, optionally assigning roles.
     *
     * The password is hashed automatically by the User model's `hashed` cast.
     * Role sync and user creation run in a single transaction so a failed role
     * assignment never leaves a half-provisioned user behind.
     *
     * The roles are re-filtered against $actor's assignable set (final authority
     * against privilege escalation, even if the FormRequest is bypassed).
     *
     * When $profile is provided, the personal-data card and its contacts/
     * addresses are persisted in the SAME transaction, so any failure rolls the
     * user back too (no half-provisioned user — ADR 0012). A null $profile keeps
     * the previous account-only behavior (backward compatible).
     *
     * When $employment is provided, the nested employment profile (spec 0015)
     * is persisted in the SAME transaction via EmploymentWriter — a null
     * $employment (the key was absent) leaves no row, matching the wire
     * contract ("employment absent or null => no row" on create).
     */
    public function create(User $actor, CreateUserData $data, ?ProfileData $profile = null, ?EmploymentData $employment = null): User
    {
        if ($profile === null) {
            // The StoreUserRequest guarantees a profile (it is the only source of
            // the derived name); a null here is a programming error / bypass.
            throw new InvalidArgumentException('A personal-data profile is required to create a user (its name is derived from the card).');
        }

        $user = DB::transaction(function () use ($actor, $data, $profile, $employment): User {
            $attributes = $data->attributes();
            // `users.name` is NOT NULL but the authoritative value is derived by
            // the shared ProfileWriter from the card (single derivation point —
            // ADR 0013). Seed only a non-null placeholder here to satisfy the
            // constraint at INSERT; writeProfile() below sets the real name.
            $attributes['name'] = '';

            $user = User::create($attributes);

            if ($data->hasRoles()) {
                $user->syncRoles($this->guard->authorizedRoleNames($actor, $data->roles));
            }

            $this->writeProfile($user, $profile);
            $this->employmentWriter->write($user, $employment);

            return $user;
        });

        return $user->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Update an existing user's attributes and, when provided, their roles and
     * password. Only keys present in $data are touched, so partial (PATCH)
     * updates leave untouched fields as-is. The password is hashed by the
     * model cast.
     *
     * The roles are re-filtered against $actor's assignable set (final authority
     * against privilege escalation, even if the FormRequest is bypassed).
     *
     * When $profile is provided, the personal-data card and its contacts/
     * addresses are upserted/synced in the SAME transaction (ADR 0012). A null
     * $profile leaves the card untouched (backward compatible).
     *
     * When $employment is provided, the nested employment profile (spec 0015)
     * is upserted/deleted in the SAME transaction via EmploymentWriter — a null
     * $employment (the key was absent) leaves the row untouched; an explicit
     * `employment: null` (EmploymentData::delete()) removes it.
     */
    public function update(User $actor, User $user, UpdateUserData $data, ?ProfileData $profile = null, ?EmploymentData $employment = null): User
    {
        $user = DB::transaction(function () use ($actor, $user, $data, $profile, $employment): User {
            $attributes = $data->submittedAttributes();

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $user->fill($attributes)->save();

            if ($data->hasRoles()) {
                $roles = $this->guard->authorizedRoleNames($actor, $data->roles);

                $this->guard->guardLastSuperAdminRoleRemoval($user, $roles);

                $user->syncRoles($roles);
            }

            $this->writeProfile($user, $profile);
            $this->employmentWriter->write($user, $employment);

            return $user;
        });

        return $user->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Persist the nested personal-data profile through the shared ProfileWriter
     * (single source of truth for the card upsert + authoritative contacts/
     * addresses sync + users.name derivation), inside the caller's transaction.
     * No-op when no profile was submitted (ADR 0013).
     */
    private function writeProfile(User $user, ?ProfileData $profile): void
    {
        $this->profileWriter->write($user, $profile);
    }

    /**
     * Delete the given user, guarding against removing the last super-admin
     * first, then against removing an operator still referenced by a lead
     * (spec 0024 BR-2/D-4), then against removing a supervisor still
     * referenced by an opportunity (spec 0040, BR-3). This guard runs
     * STRICTLY AFTER guardLastSuperAdminDeletion (spec 0040 constraint).
     */
    public function delete(User $user): void
    {
        $this->guard->guardLastSuperAdminDeletion($user);

        if ($user->leads()->exists()) {
            abort(409, 'This user is the operator on leads and cannot be deleted.');
        }

        if ($user->opportunitiesAsSupervisor()->exists()) {
            abort(409, 'This user is the supervisor on opportunities and cannot be deleted.');
        }

        $user->delete();
    }

    /**
     * Searched + paginated + hydrated user list for the for-select endpoint
     * (GET /api/users/for-select). Projects only the select fields and eager-
     * loads the optional avatar relation to avoid N+1 when the resource emits
     * `avatar_url`. Business logic for the for-select convention lives here, not
     * in the controller (ADR 0011).
     *
     * `total` reflects only the searchable population; the hydrated `ids[]` are
     * appended deduplicated AFTER the page, bypass the search filter, and do NOT
     * inflate the total (edit-mode hydration).
     *
     * `$query->operationalSiteId` (spec 0048, widened by spec 0103 D-1), when
     * set, restricts the list to users whose employment profile is a member
     * — physical OR remote — of that Sede via the
     * `employment_profile_operational_site` pivot. The employment/sites/
     * address/city relations are always eager-loaded (filtered or not) so
     * UserForSelectResource can emit the operator's PHYSICAL Sede `meta`
     * without N+1.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = User::query()
            ->select(['id', 'name', 'email'])
            ->with(['avatar', 'employment.operationalSites.addresses.city']);

        if ($query->operationalSiteId !== null) {
            $siteId = $query->operationalSiteId;
            $base->whereHas('employment.operationalSites', function (Builder $sitesQuery) use ($siteId): void {
                $sitesQuery->where('operational_sites.id', $siteId);
            });
        }

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $base->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, User> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are not
     * already on the page, deduplicated. They bypass every narrowing filter
     * (search, `operational_site_id`) — same precedent as ProductCategoryService/
     * OperationalSiteService's own hydration — but eager-load the SAME
     * employment/sites/address/city tree as the main query, so their `meta`
     * resolves without N+1. Total is unaffected.
     *
     * @param  Collection<int, User>  $page
     * @return Collection<int, User>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, User> $hydrated */
        $hydrated = User::query()
            ->select(['id', 'name', 'email'])
            ->with(['avatar', 'employment.operationalSites.addresses.city'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}

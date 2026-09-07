<?php

namespace App\Tables;

use App\Models\User;
use App\Services\UserService;
use App\Tables\Concerns\UnwrapsMultiFilter;
use App\Tables\Users\UserColumnCatalog;
use App\Tables\Users\UserEmploymentColumns;
use App\Tables\Users\UserGeoColumns;
use App\Tables\Users\UserPersonalDataColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `users` domain — the only concrete definition today.
 *
 * Carries what config/tables/users.php + the users-specific half of
 * UserTableService carried before: real User columns (id, name, email,
 * roles[derived], locale, created_at), the `roles` set filter with DYNAMIC
 * options resolved via UserService::assignableRoleNames($actor) (so a non
 * super-admin never sees `super-admin`), mapRow returning the same real fields
 * (never password/remember_token), and actionsFor calling UserPolicy
 * (view→view, update→edit, delete→delete; delete already forbids self-delete).
 *
 * Only REAL columns of the `users` table are exposed (id, name, email, locale,
 * created_at) plus the derived `roles` field. `sortable`/`filterable` below are
 * the server-side whitelist enforced by TableRowsRequest + TableService.
 *
 * The geo-derived columns (country/region/province/city) and the
 * personal-data-derived columns (user_type/primary_address/primary_contact)
 * are delegated to dedicated collaborators (UserGeoColumns /
 * UserPersonalDataColumns) — a file-size split (engineering.md §6) that keeps
 * this class a thin per-domain orchestrator over the generic Table contract.
 */
class UsersTableDefinition extends AbstractTableDefinition
{
    use UnwrapsMultiFilter;

    /**
     * Maximum number of role names honoured in the `roles` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_ROLE_FILTER_VALUES = 50;

    public function __construct(
        private readonly UserService $userService,
        private readonly UserGeoColumns $geoColumns,
        private readonly UserPersonalDataColumns $personalDataColumns,
        private readonly UserEmploymentColumns $employmentColumns,
    ) {}

    public function domain(): string
    {
        return 'users';
    }

    /**
     * @return class-string<User>
     */
    public function modelClass(): string
    {
        return User::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition already derives UserPolicy::viewAny from
    // modelClass() (users.viewAny), so the explicit override was redundant.

    /**
     * @return Builder<User>
     */
    public function baseQuery(): Builder
    {
        // Eager-load roles and the avatar relation to avoid N+1 when each row
        // resolves its role names and avatar URL. The personalData card is loaded
        // with ONLY its primary address (+ geo relations) and primary contact, so
        // mapRow reads the user_type/address/geo/contact columns entirely from
        // memory — a fixed number of queries regardless of row count.
        return User::query()
            ->with('roles', 'avatar')
            ->with([
                'personalData.addresses' => function ($query): void {
                    $query->where('is_primary', true)
                        ->with([
                            'country:id,name',
                            'state:id,name',
                            'province:id,name',
                            'city:id,name',
                        ]);
                },
                'personalData.contacts' => function ($query): void {
                    $query->where('is_primary', true);
                },
                // Employment profile (spec 0015) + its 4 relations, so mapRow
                // reads every employment-derived column entirely from memory.
                'employment.businessFunction',
                'employment.company',
                'employment.reportsTo',
                // Spec 0103 D-7: the CELL always shows the PHYSICAL site only
                // (never a remote membership), so only the pivot-scoped
                // `primaryOperationalSite` relation is eager-loaded here —
                // UserOperationalSiteColumn::label() reads it from memory.
                'employment.primaryOperationalSite' => function ($query): void {
                    $query->with(['addresses' => function ($addressQuery): void {
                        $addressQuery->where('is_primary', true)->with('city:id,name');
                    }]);
                },
            ]);
    }

    /**
     * Declarative catalogue lives in UserColumnCatalog (file-size split);
     * `user_type`'s options are the only dynamic piece (enum values).
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return UserColumnCatalog::columns($this->personalDataColumns->typeValues());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return UserColumnCatalog::filters($this->personalDataColumns->typeValues());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return UserColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Dynamic `roles` options: only roles the actor may actually assign are
     * offered, so a non super-admin never sees `super-admin` (affordance vs
     * authorization coherence). UserService remains the source of truth. Geo
     * and `user_type` options are delegated to their collaborators.
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        if ($columnId === 'roles') {
            return $this->userService->assignableRoleNames($actor);
        }

        if ($this->geoColumns->isGeoColumn($columnId)) {
            return $this->geoColumns->options($columnId);
        }

        return null;
    }

    /**
     * Badge metadata for the `user_type` column, delegated to
     * UserPersonalDataColumns.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'user_type' ? $this->personalDataColumns->typeBadges() : null;
    }

    /**
     * The `user_type` badge is driven by PersonalDataTypeEnum, exposed to the
     * frontend config under the `personal_data_type` enum key (config/config.php
     * → form_enums). Declaring it lets the frontend localize the badge label from
     * its i18n resources instead of the backend label.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'user_type' ? 'personal_data_type' : $this->employmentColumns->enumKeyFor($columnId);
    }

    /**
     * Map a User model to the row payload (real fields + derived roles only).
     * No hidden fields are ever exposed (password/remember_token). `actions` is
     * attached by the generic TableService via actionsFor(). The geo fields
     * and the personal-data-derived fields (user_type/primary_address/
     * primary_contact) are read straight off the eager-loaded primary address
     * / card (no extra queries).
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var User $row */
        $card = $row->personalData;
        $address = $card?->addresses->first();

        return array_merge([
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'avatar_url' => $row->avatarDataUri(),
            'roles' => $row->getRoleNames()->all(),
            'locale' => $row->locale,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
            'country' => $address?->country?->localizedName(),
            'region' => $address?->state?->localizedName(),
            'province' => $address?->province?->localizedName(),
            'city' => $address?->city?->localizedName(),
        ], $this->personalDataColumns->mapRow($card, $address), $this->employmentColumns->mapRow($row->employment));
    }

    /**
     * Allowed action keys for a single row, computed via UserPolicy.
     * This is the per-row source of truth reflected in row.actions[].
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        // "Login as customer" impersonation (spec 0050, AC-014): gated by the
        // same UserPolicy::impersonate the POST /users/{user}/impersonate
        // endpoint uses (users.impersonate + no escalation on a super-admin
        // target).
        if (Gate::forUser($actor)->allows('impersonate', $row)) {
            $allowed[] = 'impersonate';
        }

        return $allowed;
    }

    /**
     * Handle the derived filters (no real DB column) via the users-domain
     * collaborators: geo (country/region/province/city), personal data
     * (user_type/primary_address/primary_contact) and `roles`.
     *
     * `primary_address` is CONDITIONS-ONLY (no Set/checklist — a formatted
     * address string has no clean single-column match, a deliberate UX
     * decision, spec 0005): the flat `{filterType:'text', type:'contains',...}`
     * shape goes straight to applyAddressFilter, never through the `multi`
     * unwrap. `primary_contact` IS exposed through a `multi` widget (Set +
     * condition): unwrapDerivedFilter dispatches each present sub-model to its
     * SET/condition applier, both in AND.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($this->geoColumns->isGeoColumn($columnId)) {
            $this->geoColumns->applyFilter($query, $columnId, $filter);

            return true;
        }

        if ($this->employmentColumns->isEmploymentColumn($columnId)) {
            $this->employmentColumns->applyFilter($query, $columnId, $filter);

            return true;
        }

        return match ($columnId) {
            'roles' => $this->filterRoles($query, $filter),
            'user_type' => $this->applyPersonalDataFilter($query, fn (Builder $q): mixed => $this->personalDataColumns->applyTypeFilter($q, $filter)),
            'primary_address' => $this->applyPersonalDataFilter($query, fn (Builder $q): mixed => $this->personalDataColumns->applyAddressFilter($q, $filter)),
            'primary_contact' => $this->applyContactDerivedFilter($query, $filter),
            default => false,
        };
    }

    /**
     * `primary_contact`'s Set sub-model matches the real contact VALUE (the
     * same string the /values endpoint returns); the condition sub-model
     * (contains/equals/...) keeps working via applyContactFilter.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyContactDerivedFilter(Builder $query, array $filter): bool
    {
        $this->dispatchDerivedFilter(
            $filter,
            fn (array $set): mixed => $this->personalDataColumns->applyContactSetFilter($query, $set),
            fn (array $condition): mixed => $this->personalDataColumns->applyContactFilter($query, $condition),
        );

        return true;
    }

    /**
     * ORDER BY a derived (relation-backed) column via a correlated subquery, so
     * sorting never needs a row-multiplying JOIN on the main users query nor
     * pollutes its `users.*` selection. Each subquery yields a single scalar per
     * user (the geo name / address line / type / first contact value), or NULL
     * when the user has no card or no primary row. Delegated to the
     * geo/personal-data collaborators.
     *
     * @param  Builder<User>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($this->geoColumns->isGeoColumn($columnId)) {
            $query->orderBy($this->geoColumns->sortSubquery($columnId), $direction);

            return true;
        }

        if ($this->employmentColumns->isEmploymentColumn($columnId)) {
            $subquery = $this->employmentColumns->sortSubquery($columnId);

            if ($subquery === null) {
                return false;
            }

            $query->orderBy($subquery, $direction);

            return true;
        }

        $subquery = match ($columnId) {
            'user_type' => $this->personalDataColumns->typeSortSubquery(),
            'primary_address' => $this->personalDataColumns->addressSortSubquery(),
            'primary_contact' => $this->personalDataColumns->contactSortSubquery(),
            default => null,
        };

        if ($subquery === null) {
            return false;
        }

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived columns:
     * `roles` (scoped to the actor's assignable roles), `user_type`, the 4 geo
     * columns, and the COMPUTED `primary_contact` column (no real DB column —
     * resolved from the primary contacts of every user matching `$query`,
     * which already carries every OTHER active filter). `primary_address` is
     * CONDITIONS-ONLY (no Set/checklist, a deliberate UX decision — spec
     * 0005) and declares `hasFilterValues=false`, so TableService never calls
     * this method for it. Every other (real-column) filterable column falls
     * through to the generic engine's `SELECT DISTINCT` (return null).
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'roles') {
            $roles = $this->userService->assignableRoleNames($actor);

            $matches = $search === null || $search === ''
                ? $roles
                : array_values(array_filter($roles, static fn (string $role): bool => stripos($role, $search) !== false));

            return array_slice($matches, 0, $limit);
        }

        if ($columnId === 'user_type') {
            return $this->personalDataColumns->distinctTypeValues($search, $limit);
        }

        if ($columnId === 'primary_contact') {
            return $this->personalDataColumns->contactDistinctValues($query, $search, $limit);
        }

        if ($this->geoColumns->isGeoColumn($columnId)) {
            return $this->geoColumns->distinctValues($columnId, $search, $limit);
        }

        if ($this->employmentColumns->isEmploymentColumn($columnId)) {
            return $this->employmentColumns->distinctValues($query, $columnId, $search, $limit);
        }

        return null;
    }

    /**
     * Derived `roles` set filter via whereHas on the Spatie role relationship.
     * Only string names, capped cardinality, bound parameters — never inlined.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterRoles(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $roles = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_ROLE_FILTER_VALUES);

        if ($roles !== []) {
            $query->whereHas('roles', static function (Builder $roleQuery) use ($roles): void {
                $roleQuery->whereIn('name', $roles);
            });
        }

        return true;
    }

    /**
     * Thin adapter so the personal-data collaborator's `void`-returning
     * apply*Filter methods fit the `applyDerivedFilter` match arm (which must
     * yield the `true`/handled marker for every branch).
     *
     * @param  Builder<User>  $query
     */
    private function applyPersonalDataFilter(Builder $query, callable $apply): bool
    {
        $apply($query);

        return true;
    }

    /**
     * Delegate to UserService::delete() so the bulk-delete endpoint respects
     * the exact same last-super-admin guard as the single DELETE /users/{user}
     * endpoint (UserController::destroy).
     */
    public function deleteModel(Model $model): void
    {
        /** @var User $model */
        $this->userService->delete($model);
    }
}

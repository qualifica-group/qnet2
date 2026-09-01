<?php

namespace App\Tables;

use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use App\Tables\Concerns\UnwrapsMultiFilter;
use App\Tables\Referents\ReferentColumnCatalog;
use App\Tables\Referents\ReferentUserColumn;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `referents` domain (spec 0016).
 *
 * Real columns (name, contact_scope, created_at) are handled entirely by the
 * generic engine. `referent_type` (belongsTo) has no real DB column of its
 * own and is DERIVED: its set filter/sort/distinct-values are resolved here
 * against the related type's name, mirroring BusinessFunctionsTableDefinition's
 * `manager` derived filter. `primary_contact` is COMPUTED from the card's
 * eager-loaded contacts and behaves IDENTICALLY to the Users column, via the
 * shared PrimaryContactColumn: its row payload, text/set filter, correlated
 * sort and distinct values are all bound to the `referents` owner here.
 * `user` (belongsTo, spec 0090 D-3) is likewise DERIVED, delegated entirely to
 * ReferentUserColumn (extracted rather than inlined — this file was already
 * past the soft line limit, spec 0090 R-4).
 */
class ReferentsTableDefinition extends AbstractTableDefinition
{
    use UnwrapsMultiFilter;

    /**
     * Maximum number of names honoured in the `referent_type` set filter.
     * Caps the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(
        private readonly PrimaryContactColumn $contactColumn,
        private readonly ReferentUserColumn $userColumn,
    ) {}

    public function domain(): string
    {
        return 'referents';
    }

    /**
     * @return class-string<Referent>
     */
    public function modelClass(): string
    {
        return Referent::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ReferentPolicy::viewAny
    // from modelClass() (referents.viewAny).

    /**
     * @return Builder<Referent>
     */
    public function baseQuery(): Builder
    {
        // Eager-load referentType + the card's contacts (spec 0016 AC-015) and
        // the linked user (spec 0090, D-3), so mapRow reads referent_type/
        // primary_contact/user entirely from memory — a fixed number of
        // queries regardless of row count.
        return Referent::query()->with(['referentType', 'personalData.contacts', 'user']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ReferentColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ReferentColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ReferentColumnCatalog::actions();
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
     * Map a Referent to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Referent $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'referent_type' => $row->referentType !== null
                ? ['id' => $row->referentType->id, 'name' => $row->referentType->name]
                : null,
            'contact_scope' => $row->contact_scope->value,
            'primary_contact' => $this->contactColumn->format($row->personalData?->contacts),
            'created_at' => $row->created_at,
            'user' => $row->user?->name,
        ];
    }

    /**
     * Allowed action keys for a single row, via ReferentPolicy.
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

        return $allowed;
    }

    /**
     * Handle the derived filters (no real DB column): the `referent_type` set
     * filter (whereHas by name) and the COMPUTED `primary_contact` column,
     * which is exposed through the same `multi` widget as the Users column
     * (Set sub-model on the contact VALUE + typed condition), both applying in
     * AND. Every real column falls through to the generic engine.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return match ($columnId) {
            'referent_type' => $this->filterReferentType($query, $filter),
            'primary_contact' => $this->filterPrimaryContact($query, $filter),
            'user' => $this->userColumn->applyFilter($query, $filter),
            default => false,
        };
    }

    /**
     * Derived `referent_type` set filter via whereHas on the related type's
     * name. Only string names, capped cardinality, bound parameters.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterReferentType(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            $query->whereHas('referentType', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * Derived `primary_contact` filter: the Set sub-model matches the real
     * contact VALUE, the condition sub-model (contains/equals/...) matches
     * value/label — both via the shared column, dispatched by the `multi`
     * unwrap (mirrors UsersTableDefinition exactly).
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterPrimaryContact(Builder $query, array $filter): bool
    {
        $this->dispatchDerivedFilter(
            $filter,
            fn (array $set): mixed => $this->contactColumn->applySetFilter($query, $set),
            fn (array $condition): mixed => $this->contactColumn->applyTextFilter($query, $condition),
        );

        return true;
    }

    /**
     * ORDER BY a derived column via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query: the referent type's name
     * (`referent_type`) or the smallest primary-contact value (`primary_contact`,
     * via the shared column, identical to Users).
     *
     * @param  Builder<Referent>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === 'user') {
            $this->userColumn->applySort($query, $direction);

            return true;
        }

        $subquery = match ($columnId) {
            'referent_type' => ReferentType::query()
                ->select('name')
                ->whereColumn('referent_types.id', 'referents.referent_type_id')
                ->limit(1),
            'primary_contact' => $this->contactColumn->sortSubquery('referents', (new Referent)->getMorphClass()),
            default => null,
        };

        if ($subquery === null) {
            return false;
        }

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for `referent_type`
     * (derived: related-type NAMES), `contact_scope` (a real column, but
     * enum-cast — see below) and the COMPUTED `primary_contact` (distinct
     * primary-contact values, via the shared column), all scoped by `$query`
     * (already narrowed by every OTHER active filter).
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'referent_type' => $this->distinctReferentTypeNames($query, $search, $limit),
            'contact_scope' => $this->distinctContactScopes($query, $search, $limit),
            'primary_contact' => $this->contactColumn->distinctValues($query, 'referents', (new Referent)->getMorphClass(), $search, $limit),
            'user' => $this->userColumn->distinctValues($query, $search, $limit),
            default => null,
        };
    }

    /**
     * @param  Builder<Referent>  $query
     * @return array<int, string>
     */
    private function distinctReferentTypeNames(Builder $query, ?string $search, int $limit): array
    {
        $typeIds = (clone $query)->whereNotNull('referent_type_id')->select('referent_type_id');

        return DB::table('referent_types')
            ->whereIn('id', $typeIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * `contact_scope` is a real column, but Eloquent's `pluck()` hydrates it
     * through the enum cast (an uncastable-to-string BackedEnum instance) —
     * `toBase()` reads the raw query builder instead, bypassing the cast, so
     * the generic engine's fallback is intentionally NOT reused here.
     *
     * @param  Builder<Referent>  $query
     * @return array<int, string>
     */
    private function distinctContactScopes(Builder $query, ?string $search, int $limit): array
    {
        $clone = (clone $query)->toBase();

        if ($search !== null && $search !== '') {
            $clone->where('contact_scope', 'like', '%'.$this->escapeLike($search).'%');
        }

        return $clone->whereNotNull('contact_scope')
            ->distinct()
            ->orderBy('contact_scope')
            ->limit($limit)
            ->pluck('contact_scope')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

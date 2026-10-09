<?php

namespace App\Tables;

use App\Models\Registry;
use App\Models\User;
use App\Tables\Registries\RegistryCellWriter;
use App\Tables\Registries\RegistryColumnCatalog;
use App\Tables\Registries\RegistryRelationColumns;
use App\Tables\Registries\RegistrySearch;
use App\Tables\Shared\PersonalDataTypeColumn;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `registries` domain (spec 0020, "Anagrafiche").
 *
 * Real columns (name, is_supplier, agreement_status, size_class, created_at)
 * are handled entirely by the generic engine for sort/set-filter; only their
 * distinct-values need a definition override for the cast-bearing three
 * (is_supplier/agreement_status/size_class — `pluck()` through the model
 * cast would hydrate an uncastable-to-string bool/BackedEnum, mirroring
 * ReferentsTableDefinition's `distinctContactScopes`). `source`/
 * `commercial`/`supervisor`/`reporter` (belongsTo) and `managers` (the
 * `registry_user` pivot, shown as "Operatori") have no real DB column of
 * their own and are DERIVED: their set filter/sort/distinct-values are
 * resolved by RegistryRelationColumns against the related row's name,
 * mirroring OpportunityRelationColumns. `primary_contact` is
 * COMPUTED from the card's eager-loaded contacts via the shared
 * PrimaryContactColumn, display-only here (neither sortable nor filterable —
 * spec 0020 data contract, unlike the identical Users/Referents column).
 * `registry_type` (person vs company, from the card's type) is DERIVED via
 * the shared PersonalDataTypeColumn, exactly like the Users `user_type`.
 */
class RegistriesTableDefinition extends AbstractTableDefinition
{
    /** Real enum columns whose id is also their config enum key (config/config.php). */
    private const array ENUM_COLUMNS = ['agreement_status', 'size_class'];

    private const string TYPE_COLUMN = 'registry_type';

    public function __construct(
        private readonly PrimaryContactColumn $contactColumn,
        private readonly RegistryRelationColumns $relationColumns,
        private readonly RegistryCellWriter $cellWriter,
        private readonly PersonalDataTypeColumn $typeColumn,
        private readonly RegistrySearch $search,
    ) {}

    /**
     * Spec 0206: the enum editors of `agreement_status`/`size_class` label
     * their options from the same config enums the form's selects read.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        if ($columnId === self::TYPE_COLUMN) {
            return 'personal_data_type';
        }

        return in_array($columnId, self::ENUM_COLUMNS, true) ? $columnId : null;
    }

    /**
     * Badge metadata of the `registry_type` column (PersonalDataTypeEnum).
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === self::TYPE_COLUMN ? $this->typeColumn->badges() : null;
    }

    /**
     * Spec 0206, D-2: the inline cell edit follows the form's rules —
     * UpdateRegistryRequest + RegistryService::update() through
     * RegistryCellWriter, never the generic `$row->update()`.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var Registry $row */
        /** @var User $actor */
        $actor = Auth::user();

        return $this->cellWriter->write($row, $columnId, $value, $actor);
    }

    public function domain(): string
    {
        return 'registries';
    }

    /**
     * @return class-string<Registry>
     */
    public function modelClass(): string
    {
        return Registry::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives RegistryPolicy::viewAny
    // from modelClass() (registries.viewAny).

    /**
     * @return Builder<Registry>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches (spec 0020 AC-015), so
        // each row is read entirely from memory — a fixed number of queries
        // regardless of row count. supervisor/managers pull their avatar too,
        // so the person cells render a real avatar without a per-row query.
        return Registry::query()
            ->with(['source', 'commercial', 'supervisor.avatar', 'reporter', 'managers.avatar', 'personalData.contacts'])
            // Per-row count for the `documents` action badge (spec 0173),
            // scoped to the 'documents' collection only, as Opportunita'.
            ->withCount(['attachments as documents_count' => fn (Builder $q) => $q->where('collection', 'documents')]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return RegistryColumnCatalog::columns($this->typeColumn->values());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return RegistryColumnCatalog::filters($this->typeColumn->values());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return RegistryColumnCatalog::actions();
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
     * Map a Registry to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Registry $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            self::TYPE_COLUMN => $row->personalData?->type?->value,
            'source' => $this->summarize($row->source),
            'is_supplier' => $row->is_supplier,
            'agreement_status' => $row->agreement_status?->value,
            'size_class' => $row->size_class?->value,
            'primary_contact' => $this->contactColumn->format($row->personalData?->contacts),
            'commercial' => $this->summarize($row->commercial),
            'supervisor' => $this->userSummary($row->supervisor),
            'reporter' => $this->summarize($row->reporter),
            'managers' => $row->managers->map(fn (User $user): array => $this->userSummary($user))->all(),
            'created_at' => $row->created_at,
            'documents_count' => (int) ($row->documents_count ?? 0),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * A person summary carrying the inline avatar (data URI), so the
     * supervisor and managers cells render a real avatar, not just initials —
     * mirrors OpportunitiesTableDefinition::userSummary(). Null when unset.
     *
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function userSummary(?User $user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }

    /**
     * Allowed action keys for a single row, via RegistryPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('viewDocuments', $row)) {
            $allowed[] = 'documents';
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
     * Spec 0211: the quick-search on `name` also matches the card (VAT
     * number), the phones and the linked referents through one extra OR
     * branch; `name` itself keeps the engine's LIKE (nothing covered).
     *
     * @param  Builder<Registry>  $query
     * @param  array<int, string>  $columnIds
     * @return array<int, string>
     */
    public function applyGroupedSearch(Builder $query, array $columnIds, string $term): array
    {
        return $this->search->apply($query, $columnIds, $term);
    }

    /**
     * `registry_type` goes to the shared PersonalDataTypeColumn, the
     * relation-derived columns (`source`/`commercial`/`supervisor`/
     * `reporter`/`managers`) to RegistryRelationColumns; every real column
     * falls through to the generic engine.
     *
     * @param  Builder<Registry>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::TYPE_COLUMN) {
            $this->typeColumn->applyFilter($query, $filter);

            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<Registry>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::TYPE_COLUMN) {
            $query->orderBy($this->typeColumn->sortSubquery('registries', (new Registry)->getMorphClass()), $direction);

            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the relation-derived
     * columns via RegistryRelationColumns, the three cast-bearing real
     * columns (`is_supplier`, `agreement_status`, `size_class`) here, all
     * scoped by `$query` (already narrowed by every OTHER active filter).
     *
     * @param  Builder<Registry>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'is_supplier', 'agreement_status', 'size_class' => $this->distinctRawColumn($query, $columnId, $search, $limit),
            self::TYPE_COLUMN => $this->typeColumn->distinctValues($search, $limit),
            default => $this->relationColumns->distinctValues($columnId, $search, $query, $limit),
        };
    }

    /**
     * `is_supplier`/`agreement_status`/`size_class` are real columns, but
     * Eloquent's `pluck()` would hydrate them through their bool/enum cast
     * (an uncastable-to-string value for the Set Filter) — `toBase()` reads
     * the raw query builder instead, bypassing the cast, mirroring
     * ReferentsTableDefinition's `distinctContactScopes`.
     *
     * @param  Builder<Registry>  $query
     * @return array<int, string>
     */
    private function distinctRawColumn(Builder $query, string $columnId, ?string $search, int $limit): array
    {
        $clone = (clone $query)->toBase();

        if ($search !== null && $search !== '') {
            $clone->where($columnId, 'like', '%'.$this->escapeLike($search).'%');
        }

        $values = $clone->whereNotNull($columnId)
            ->where($columnId, '<>', '')
            ->distinct()
            ->orderBy($columnId)
            ->limit($limit)
            ->pluck($columnId)
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->where(static function (Builder $group) use ($columnId): void {
                $group->whereNull($columnId)->orWhere($columnId, '=', '');
            })
            ->exists());
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

<?php

namespace App\Tables;

use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Tables\Registries\RegistryColumnCatalog;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `registries` domain (spec 0020, "Anagrafiche").
 *
 * Real columns (name, is_supplier, agreement_status, size_class, created_at)
 * are handled entirely by the generic engine for sort/set-filter; only their
 * distinct-values need a definition override for the cast-bearing three
 * (is_supplier/agreement_status/size_class — `pluck()` through the model
 * cast would hydrate an uncastable-to-string bool/BackedEnum, mirroring
 * ReferentsTableDefinition's `distinctContactScopes`). `source` (belongsTo)
 * has no real DB column of its own and is DERIVED: its set filter/sort/
 * distinct-values are resolved here against the related source's name,
 * mirroring ReferentsTableDefinition's `referent_type`. `primary_contact` is
 * COMPUTED from the card's eager-loaded contacts via the shared
 * PrimaryContactColumn, display-only here (neither sortable nor filterable —
 * spec 0020 data contract, unlike the identical Users/Referents column).
 */
class RegistriesTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `source` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly PrimaryContactColumn $contactColumn) {}

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
        // Eager-load source + the card's contacts (spec 0020 AC-015), so
        // mapRow reads source/primary_contact entirely from memory — a fixed
        // number of queries regardless of row count.
        return Registry::query()->with(['source', 'personalData.contacts']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return RegistryColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return RegistryColumnCatalog::filters();
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
            'source' => $row->source !== null
                ? ['id' => $row->source->id, 'name' => $row->source->name]
                : null,
            'is_supplier' => $row->is_supplier,
            'agreement_status' => $row->agreement_status?->value,
            'size_class' => $row->size_class?->value,
            'primary_contact' => $this->contactColumn->format($row->personalData?->contacts),
            'created_at' => $row->created_at,
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
     * Handle the derived `source` filter (no real DB column): a set filter
     * (whereHas by name) applied in AND. Every real column falls through to
     * the generic engine.
     *
     * @param  Builder<Registry>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'source') {
            return false;
        }

        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($names === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(static function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas('source', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('name', $names);
                });
            }

            // The blank entry ("(Vuoti)"): the registries with no source.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('source');
            }
        });

        return true;
    }

    /**
     * ORDER BY the derived `source` column via a correlated subquery, so
     * sorting never needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<Registry>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'source') {
            return false;
        }

        $query->orderBy(
            Source::query()
                ->select('name')
                ->whereColumn('sources.id', 'registries.source_id')
                ->limit(1),
            $direction,
        );

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for `source` (derived:
     * related source NAMES) and the three cast-bearing real columns
     * (`is_supplier`, `agreement_status`, `size_class`), all scoped by
     * `$query` (already narrowed by every OTHER active filter).
     *
     * @param  Builder<Registry>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'source' => $this->distinctSourceNames($query, $search, $limit),
            'is_supplier', 'agreement_status', 'size_class' => $this->distinctRawColumn($query, $columnId, $search, $limit),
            default => null,
        };
    }

    /**
     * @param  Builder<Registry>  $query
     * @return array<int, string>
     */
    private function distinctSourceNames(Builder $query, ?string $search, int $limit): array
    {
        $sourceIds = (clone $query)->whereNotNull('source_id')->select('source_id');

        $values = DB::table('sources')
            ->whereIn('id', $sourceIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave('source')
            ->exists());
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

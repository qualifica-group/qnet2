<?php

namespace App\Tables;

use App\Models\Sector;
use App\Models\User;
use App\Services\SectorService;
use App\Tables\Sectors\SectorColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `sectors` domain (spec 0018): the AG Grid
 * SSRM list view, alongside the dedicated tree view (GET /sectors/tree,
 * used by the future parent picker).
 *
 * Real columns (name, created_at) are handled entirely by the generic
 * engine. `parent` is DERIVED (no real DB column — the related parent's
 * name), mirroring ProductCategoriesTableDefinition's `parent` column.
 */
class SectorsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `parent` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly SectorService $service) {}

    public function domain(): string
    {
        return 'sectors';
    }

    /**
     * @return class-string<Sector>
     */
    public function modelClass(): string
    {
        return Sector::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives SectorPolicy::viewAny
    // from modelClass() (sectors.viewAny).

    /**
     * @return Builder<Sector>
     */
    public function baseQuery(): Builder
    {
        // Eager-load parent (no N+1 across the page's rows).
        return Sector::query()->with(['parent']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return SectorColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return SectorColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return SectorColumnCatalog::actions();
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
     * Map a Sector to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Sector $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'parent' => $this->parentSummary($row->parent),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function parentSummary(?Sector $parent): ?array
    {
        if ($parent === null) {
            return null;
        }

        return ['id' => $parent->id, 'name' => $parent->name];
    }

    /**
     * Allowed action keys for a single row, via SectorPolicy.
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
     * Handle the derived `parent` set filter. Every other column id (the
     * real columns) falls through to the generic engine.
     *
     * @param  Builder<Sector>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'parent') {
            return false;
        }

        return $this->filterByParentName($query, $filter);
    }

    /**
     * Derived set filter via whereHas on the self-referencing `parent`
     * relation, matched by name. Bound parameters, capped cardinality.
     *
     * @param  Builder<Sector>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByParentName(Builder $query, array $filter): bool
    {
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
                $group->whereHas('parent', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('name', $names);
                });
            }

            // The blank entry ("(Vuoti)"): the root sectors, with no parent.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('parent');
            }
        });

        return true;
    }

    /**
     * ORDER BY the parent's name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<Sector>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'parent') {
            return false;
        }

        // Self-join: the subquery's own table is aliased (`parent_sector`) so
        // it never collides with the outer query's `sectors`.
        $subquery = Sector::query()
            ->from('sectors as parent_sector')
            ->select('parent_sector.name')
            ->whereColumn('parent_sector.id', 'sectors.parent_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived `parent`
     * column.
     *
     * @param  Builder<Sector>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId !== 'parent') {
            return null;
        }

        return $this->distinctParentNames($query, $search, $limit);
    }

    /**
     * @param  Builder<Sector>  $query
     * @return array<int, string>
     */
    private function distinctParentNames(Builder $query, ?string $search, int $limit): array
    {
        $parentIds = (clone $query)->whereNotNull('parent_id')->select('parent_id');

        $values = DB::table('sectors')
            ->whereIn('id', $parentIds)
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
            ->whereDoesntHave('parent')
            ->exists());
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Delegate to SectorService::delete() so the generic bulk-delete
     * endpoint respects the exact same restrictive-delete guard (children)
     * as the single DELETE /sectors/{sector} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var Sector $model */
        $this->service->delete($model);
    }
}

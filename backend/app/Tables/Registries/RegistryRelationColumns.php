<?php

namespace App\Tables\Registries;

use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The relation-derived column machinery for the `registries` domain,
 * extracted out of RegistriesTableDefinition (file-size split,
 * engineering.md §6), mirroring OpportunityRelationColumns:
 * `source`/`commercial`/`supervisor`/`reporter` (own-FK, simple
 * relation-name columns) and `managers` (`registry_user` pivot, to-many, the
 * "Operatori" column) — a `whereHas` set filter (allow-listed columns only,
 * never orderByRaw/whereRaw on raw input — backend.md §8), a correlated
 * subquery sort for the simple relations, and Excel-like distinct values
 * (spec 0004/0005).
 */
final class RegistryRelationColumns
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    private const string MANAGERS_COLUMN = 'managers';

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id.
     * `commercial`/`reporter` are external referents, `supervisor` an
     * internal user (Registry::supervisor()).
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
        'commercial' => ['relation' => 'commercial', 'table' => 'referents', 'fk' => 'commercial_id'],
        'supervisor' => ['relation' => 'supervisor', 'table' => 'users', 'fk' => 'supervisor_id'],
        'reporter' => ['relation' => 'reporter', 'table' => 'referents', 'fk' => 'reporter_id'],
    ];

    /**
     * Handle the simple-relation set filters and the `managers` pivot set
     * filter, all via `whereHas` on the related row's name. Returns false for
     * any other column id (falls through to the generic engine).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        $relation = $columnId === self::MANAGERS_COLUMN
            ? self::MANAGERS_COLUMN
            : (self::DERIVED_RELATIONS[$columnId]['relation'] ?? null);

        if ($relation === null) {
            return false;
        }

        $values = $this->filterValues($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(static function (Builder $group) use ($relation, $values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas($relation, static function (Builder $relatedQuery) use ($values): void {
                    $relatedQuery->whereIn('name', $values);
                });
            }

            // The blank entry ("(Vuoti)"): the registries with no such related row.
            if ($matchesBlank) {
                $group->orWhereDoesntHave($relation);
            }
        });

        return true;
    }

    /**
     * ORDER BY the related row's name via a correlated subquery, so sorting
     * never needs a row-multiplying JOIN on the main query. `managers` is
     * not sortable (no single related row to order by).
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $query->orderBy(
            DB::table($config['table'])
                ->select('name')
                ->whereColumn("{$config['table']}.id", "registries.{$config['fk']}")
                ->limit(1),
            $direction,
        );

        return true;
    }

    /**
     * Excel-like distinct values: the related row's name for each simple
     * relation, the `registry_user` pivot's user names for `managers` — all
     * scoped by `$query` (already narrowed by every OTHER active filter).
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::MANAGERS_COLUMN) {
            // "No manager" is answered by the pivot alone: `user_id` cascades
            // on delete, so the join to `users` whereDoesntHave() adds is pure cost.
            return $this->withBlankEntry(
                $this->distinctManagerNames($search, $query, $limit),
                $search,
                fn (): bool => (clone $query)->whereNotExists(static function (QueryBuilder $pivot): void {
                    $pivot->selectRaw('1')->from('registry_user')->whereColumn('registry_user.registry_id', 'registries.id');
                })->exists(),
            );
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->whereNotNull($config['fk'])->select($config['fk']);

        $values = DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->groupBy('name')
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave($config['relation'])
            ->exists());
    }

    /**
     * Distinct account-manager names among the registries matching $query,
     * EXISTS per user rather than a join + DISTINCT over the pivot.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctManagerNames(?string $search, Builder $query, int $limit): array
    {
        $registryIds = (clone $query)->select('registries.id');

        return DB::table('users')
            ->whereExists(static function (QueryBuilder $pivot) use ($registryIds): void {
                $pivot->selectRaw('1')->from('registry_user')
                    ->whereColumn('registry_user.user_id', 'users.id')
                    ->whereIn('registry_user.registry_id', $registryIds);
            })
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('users.name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->groupBy('users.name')
            ->orderBy('users.name')
            ->limit($limit)
            ->pluck('users.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

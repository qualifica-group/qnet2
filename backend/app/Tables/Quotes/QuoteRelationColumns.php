<?php

namespace App\Tables\Quotes;

use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The GENERIC relation-derived column machinery for the `quotes` domain
 * (spec 0065, MT-05), extracted out of QuotesTableDefinition (file-size
 * split, engineering.md §6): `opportunity`/`quote_workflow_status`/
 * `commercial`/`reporter`/`supervisor`/`company`/`company_site` (own-FK,
 * simple relation-label columns) and `managers` (`quote_user` pivot,
 * to-many, spec 0087 D-1/T-10) — a `whereHas` set filter (allow-listed
 * columns only, never orderByRaw/whereRaw on raw input — backend.md §8), a
 * correlated subquery sort for the simple relations, and Excel-like distinct
 * values (spec 0004/0005), mirroring OpportunityRelationColumns.
 */
final class QuoteRelationColumns
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The label column of a related row, used unless the relation overrides
     * it: every table here but `companies` calls it `name`.
     */
    private const string DEFAULT_LABEL_COLUMN = 'name';

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table, owning FK column and — only where it is not `name` —
     * the related row's label column, keyed by the derived column id.
     *
     * `company` (user directive 2026-07-30) is the one entry with a
     * different label column: `companies` has no `name`, its display name is
     * `denomination` (spec 0010). `operational_site` is deliberately ABSENT:
     * the site has no label COLUMN at all (its identity is its primary
     * address), so it is delegated to the shared OperationalSiteColumn by
     * QuotesTableDefinition, exactly as on Opportunities.
     *
     * @var array<string, array{relation: string, table: string, fk: string, label?: string}>
     */
    private const array DERIVED_RELATIONS = [
        'opportunity' => ['relation' => 'opportunity', 'table' => 'opportunities', 'fk' => 'opportunity_id'],
        'quote_workflow_status' => ['relation' => 'quoteWorkflowStatus', 'table' => 'quote_workflow_statuses', 'fk' => 'quote_workflow_status_id'],
        'commercial' => ['relation' => 'commercial', 'table' => 'referents', 'fk' => 'commercial_id'],
        'reporter' => ['relation' => 'reporter', 'table' => 'referents', 'fk' => 'reporter_id'],
        'supervisor' => ['relation' => 'supervisor', 'table' => 'users', 'fk' => 'supervisor_id'],
        'company' => ['relation' => 'company', 'table' => 'companies', 'fk' => 'company_id', 'label' => 'denomination'],
        'company_site' => ['relation' => 'companySite', 'table' => 'company_sites', 'fk' => 'company_site_id'],
    ];

    /**
     * Handle every derived column's `set` filter via `whereHas` on the
     * related row's name, PLUS the `managers` (`quote_user` pivot, to-many)
     * set filter the same way. Returns false for any other column id (falls
     * through to the generic engine).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if ($columnId === 'managers') {
            $this->applyLabelFilter($query, 'managers', 'name', $filter);

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $this->applyLabelFilter($query, $config['relation'], $this->labelColumn($config), $filter);

        return true;
    }

    /**
     * Match the rows whose `$relation` has a row labelled among the filter's
     * values, plus — when the blank entry ("(Vuoti)") is ticked — the rows
     * with no such related row at all, which is what an empty cell means for
     * every relation-derived column.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyLabelFilter(Builder $query, string $relation, string $label, array $filter): void
    {
        $values = $this->filterValues($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($relation, $label, $values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas($relation, static function (Builder $relatedQuery) use ($label, $values): void {
                    $relatedQuery->whereIn($label, $values);
                });
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave($relation);
            }
        });
    }

    /**
     * ORDER BY the related row's name via a correlated subquery. `managers`
     * is NOT sortable (returns false — no single related row to order by,
     * mirrors OpportunityRelationColumns).
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select($this->labelColumn($config))
            ->whereColumn("{$config['table']}.id", "quotes.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the related row's name for
     * the simple-relation derived columns, plus the `managers` pivot's user
     * names, scoped to the rows matching $query.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'managers') {
            // "No manager" is answered by the pivot alone: `user_id` cascades on
            // delete, so the join to `users` that whereDoesntHave() adds is pure cost
            // (5.2s against 3.7s on 1M offers).
            return $this->withBlankEntry(
                $this->distinctManagerNames($search, $query, $limit),
                $search,
                fn (): bool => (clone $query)->whereNotExists(static function (QueryBuilder $pivot): void {
                    $pivot->selectRaw('1')->from('quote_user')->whereColumn('quote_user.quote_id', 'quotes.id');
                })->exists(),
            );
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->whereNotNull($config['fk'])->select($config['fk']);
        $label = $this->labelColumn($config);

        $values = DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($label, $search): void {
                $builder->where($label, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->groupBy($label)
            ->orderBy($label)
            ->limit($limit)
            ->pluck($label)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlanks($values, $config['relation'], $search, $query);
    }

    /**
     * Offer the blank entry when some scoped row has no related row on
     * `$relation` — the one definition of "empty cell" every column here
     * shares.
     *
     * @param  array<int, string|null>  $values
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    private function withBlanks(array $values, string $relation, ?string $search, Builder $query): array
    {
        return $this->withBlankEntry(
            $values,
            $search,
            fn (): bool => (clone $query)->whereDoesntHave($relation)->exists(),
        );
    }

    /**
     * Distinct account-manager names among the offers matching $query, via a
     * join through the `quote_user` pivot — scoped by every OTHER active
     * filter (Excel-like distinct values, spec 0004/0005), mirrors
     * OpportunityRelationColumns::distinctManagerNames() verbatim over the
     * Offerta's own pivot.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctManagerNames(?string $search, Builder $query, int $limit): array
    {
        $quoteIds = (clone $query)->select('quotes.id');

        // EXISTS per user rather than a join + DISTINCT: the join fanned 3M pivot
        // rows out before de-duplicating a few hundred names.
        return DB::table('users')
            ->whereExists(static function (QueryBuilder $pivot) use ($quoteIds): void {
                $pivot->selectRaw('1')->from('quote_user')
                    ->whereColumn('quote_user.user_id', 'users.id')
                    ->whereIn('quote_user.quote_id', $quoteIds);
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
     * `whereHas` on a relation's own `name`, bound and never raw — shared by
     * a derived-column set filter (applyFilter) and its advanced-filter twin
     * (QuotesTableDefinition::applyAdvancedFilter, `quote_workflow_status`).
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyNameWhereHas(Builder $query, string $relation, array $values): void
    {
        $query->whereHas($relation, static function (Builder $relatedQuery) use ($values): void {
            $relatedQuery->whereIn('name', $values);
        });
    }

    /**
     * The related row's label column. Comes from the static DERIVED_RELATIONS
     * map only — never from request input, so it is safe as a column
     * identifier (backend.md §8: allow-list, never raw input).
     *
     * @param  array{relation: string, table: string, fk: string, label?: string}  $config
     */
    private function labelColumn(array $config): string
    {
        return $config['label'] ?? self::DEFAULT_LABEL_COLUMN;
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

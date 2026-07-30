<?php

namespace App\Tables\Quotes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The GENERIC relation-derived column machinery for the `quotes` domain
 * (spec 0065, MT-05), extracted out of QuotesTableDefinition (file-size
 * split, engineering.md §6): `opportunity`/`quote_status`/`commercial`/
 * `reporter`/`supervisor`/`company`/`company_site` (own-FK, simple
 * relation-label columns) — a
 * `whereHas` set filter (allow-listed columns only, never orderByRaw/
 * whereRaw on raw input — backend.md §8), a correlated subquery sort, and
 * Excel-like distinct values (spec 0004/0005), mirroring
 * OpportunityRelationColumns.
 */
final class QuoteRelationColumns
{
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
        'quote_status' => ['relation' => 'quoteStatus', 'table' => 'quote_statuses', 'fk' => 'quote_status_id'],
        'commercial' => ['relation' => 'commercial', 'table' => 'referents', 'fk' => 'commercial_id'],
        'reporter' => ['relation' => 'reporter', 'table' => 'referents', 'fk' => 'reporter_id'],
        'supervisor' => ['relation' => 'supervisor', 'table' => 'users', 'fk' => 'supervisor_id'],
        'company' => ['relation' => 'company', 'table' => 'companies', 'fk' => 'company_id', 'label' => 'denomination'],
        'company_site' => ['relation' => 'companySite', 'table' => 'company_sites', 'fk' => 'company_site_id'],
    ];

    /**
     * Handle every derived column's `set` filter via `whereHas` on the
     * related row's name. Returns false for any other column id (falls
     * through to the generic engine).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $this->filterValues($filter);

        if ($values !== []) {
            $label = $this->labelColumn($config);

            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($label, $values): void {
                $relatedQuery->whereIn($label, $values);
            });
        }

        return true;
    }

    /**
     * ORDER BY the related row's name via a correlated subquery.
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
     * Excel-like distinct values (spec 0004/0005): the related row's name,
     * scoped to the rows matching $query.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->whereNotNull($config['fk'])->select($config['fk']);
        $label = $this->labelColumn($config);

        return DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($label, $search): void {
                $builder->where($label, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy($label)
            ->limit($limit)
            ->pluck($label)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
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

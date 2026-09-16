<?php

namespace App\Tables\Shared;

use App\Models\Address;
use App\Models\OperationalSite;
use App\Support\OperationalSiteLabel;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The COMPUTED, to-one `operational_site` table column (spec 0056), shared by
 * every domain whose model owns a single `operational_site_id` FK —
 * Opportunities and Gestione Richieste today (both against the same
 * `opportunities` table/model). Mirrors LeadOperationalSiteColumn's identity
 * convention: the site has no own name column, so sort/filter/distinct-values
 * all pass through its PRIMARY address `line1` (never the composed display
 * label, which is not a SQL column).
 *
 * $relation/$table/$fkColumn are the ONLY bindings that differ between
 * domains — every other rule (is_primary scoping, bound LIKE, capped
 * cardinality) is domain-agnostic and lives here once, mirroring
 * PrimaryContactColumn's convention. `leads` keeps its own
 * LeadOperationalSiteColumn for now (spec 0056 out-of-scope: migrating it is a
 * follow-up, not this round), but this class's parametrization is a drop-in
 * for that migration later.
 */
final class OperationalSiteColumn
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of values honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @return array{id: int, label: string}|null
     */
    public function summarize(?OperationalSite $site): ?array
    {
        return OperationalSiteLabel::summarize($site);
    }

    /**
     * Match rows whose site's primary address `line1` is among $values
     * (bound parameters, no raw SQL). `$matchesBlank` adds the rows the value
     * list cannot reach at all — no site, or a site with no primary `line1` —
     * i.e. the ones whose cell reads empty.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, string $relation, array $values, bool $matchesBlank = false): void
    {
        $values = array_slice($values, 0, self::MAX_FILTER_VALUES);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($relation, $values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas("{$relation}.addresses", static function (Builder $addressQuery) use ($values): void {
                    $addressQuery->where('is_primary', true)->whereIn('line1', $values);
                });
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave("{$relation}.addresses", static function (Builder $addressQuery): void {
                    $addressQuery->where('is_primary', true)->whereNotNull('line1');
                });
            }
        });
    }

    /**
     * Advanced-filter (spec 0032) free-text search on the site's primary
     * address `line1` — a bound, escaped LIKE.
     *
     * @param  Builder<Model>  $query
     */
    public function applyAdvancedFilter(Builder $query, string $relation, string $needle): void
    {
        $pattern = '%'.$this->escapeLike($needle).'%';

        $query->whereHas("{$relation}.addresses", static function (Builder $addressQuery) use ($pattern): void {
            $addressQuery->where('is_primary', true)
                ->whereRaw('line1 LIKE ? '.self::LIKE_ESCAPE_CLAUSE, [$pattern]);
        });
    }

    /**
     * ORDER BY the row's site's primary address `line1` via a correlated
     * subquery.
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $table, string $fkColumn, string $direction): void
    {
        $query->orderBy($this->sortSubquery($table, $fkColumn), $direction);
    }

    /**
     * Distinct primary-address `line1` values among the sites referenced by
     * rows matching $query (already scoped by every other active filter),
     * optionally narrowed by a case-insensitive substring search, plus the
     * blank entry when some scoped row has no reachable value at all.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    public function distinctValues(Builder $query, string $fkColumn, string $relation, ?string $search, int $limit): array
    {
        $siteIds = (clone $query)->whereNotNull($fkColumn)->select($fkColumn);

        $values = Address::query()
            ->whereIn('addressable_id', $siteIds)
            ->where('addressable_type', (new OperationalSite)->getMorphClass())
            ->where('is_primary', true)
            ->whereNotNull('line1')
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->whereRaw('line1 LIKE ? '.self::LIKE_ESCAPE_CLAUSE, ['%'.$this->escapeLike($search).'%']);
            })
            ->distinct()
            ->orderBy('line1')
            ->limit($limit)
            ->pluck('line1')
            ->map(static fn (mixed $line1): string => (string) $line1)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave("{$relation}.addresses", static function (Builder $addressQuery): void {
                $addressQuery->where('is_primary', true)->whereNotNull('line1');
            })
            ->exists());
    }

    /**
     * Subquery selecting the referencing row's site's primary address
     * `line1`.
     *
     * @return Builder<Address>
     */
    private function sortSubquery(string $table, string $fkColumn): Builder
    {
        return Address::query()
            ->select('line1')
            ->whereColumn('addresses.addressable_id', "{$table}.{$fkColumn}")
            ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->limit(1);
    }

    /**
     * Makes the backslash of escapeLike() actually mean "escape". MySQL treats
     * it that way by default, SQLite (dev/test, see CLAUDE.md §0) does NOT: a
     * plain `LIKE '%100\%%'` there matches NOTHING, so an address containing a
     * literal `%` or `_` became silently unfindable. Stating it explicitly is
     * correct on both engines. The fragment carries no user input — only the
     * static column and clause; the needle stays a bound parameter.
     */
    private const string LIKE_ESCAPE_CLAUSE = "ESCAPE '\\'";

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

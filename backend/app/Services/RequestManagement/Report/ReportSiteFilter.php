<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * WHICH Sede operativa the report and the dashboard are computed on (spec
 * 0112, D-1): the ONE place that knows how to turn the selected `site_keys`
 * into SQL. A separate class from ReportOperatorFilter on purpose (D-9) —
 * two independent axes ANDed together in ReportBranchQuery, whose SQL
 * translations share no line: one is a `whereIn` on a column of the row, this
 * one a correlated `whereExists` across two tables.
 *
 * The Sede of a request is the Sede of its GA2 Operatore (D-2), never
 * `quotes.operational_site_id` (the OFFER's own site, which stays the
 * business of RequestManagementScope's visibility rule): the chain walked
 * here is `quotes.operator_id` -> `employment_profiles.user_id` -> the
 * `employment_profile_operational_site` pivot. The pivot is read WHOLE,
 * physical and remote memberships alike (D-5, no `is_primary` predicate) —
 * the same rule RequestManagementScope::actorSiteIds() already applies.
 *
 * A correlated `whereExists`, never a join (constraints): a join to the pivot
 * would fan a quote out once per membership and over-count anything joined on
 * top of it — "N. Telefonate Effettuate" counts `notes.id`. Existential means
 * a request whose GA2 belongs to three selected Sedi is counted ONCE, which
 * is what makes `site_keys=[A]`, `[B]` and `[A,B]` all agree (AC-004).
 *
 * `all()` is the default and touches nothing: absent `site_keys` means every
 * Sede (D-4) — the semantics an ExportRun frozen before this spec relies on,
 * and the reason no existing caller had to change its behaviour.
 *
 * The predicate is wrapped in its OWN `where()` closure and never hung off
 * the builder's root with a bare `orWhere`: callers hand this a query that
 * already carries the branch's category match AND RequestManagementScope's
 * visibility rule, and a root-level OR would escape both — widening the
 * perimeter instead of narrowing it. Applied AFTER the scope for the same
 * reason (AC-008): narrowing an already-scoped query can never reveal a row
 * the actor could not see.
 *
 * Fail-closed by construction, twice over (D-6): a restricted filter with no
 * ids compiles to `whereIn(..., [])`, which matches nothing; and the
 * correlated match never finds a row when `quotes.operator_id` is NULL ("Non
 * assegnato") or when the operator has no membership at all — the two cases
 * the user excluded, excluded by the SAME structure rather than by two
 * separate checks.
 */
final class ReportSiteFilter
{
    private const string PIVOT_TABLE = 'employment_profile_operational_site';

    /**
     * @param  array<int, int>  $siteIds
     */
    private function __construct(
        private readonly bool $unrestricted,
        private readonly array $siteIds,
    ) {}

    public static function all(): self
    {
        return new self(true, []);
    }

    /**
     * @param  array<int, string>  $keys  operational site ids as strings
     */
    public static function fromKeys(array $keys): self
    {
        return new self(
            unrestricted: false,
            siteIds: array_values(array_unique(array_map(intval(...), $keys))),
        );
    }

    /**
     * The wire-level rule of D-4, in one place: a MISSING selection is every
     * Sede, an EMPTY one is nobody. Validation (`min:1`) keeps the second
     * case off the wire; it is honoured here rather than silently widened.
     *
     * @param  array<int, string>|null  $keys
     */
    public static function fromKeysOrAll(?array $keys): self
    {
        return $keys === null ? self::all() : self::fromKeys($keys);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyTo(Builder $query): Builder
    {
        if ($this->unrestricted) {
            return $query;
        }

        return $query->where(function (Builder $selected): void {
            $selected->whereExists(function (QueryBuilder $sub): void {
                $sub->selectRaw('1')
                    ->from('employment_profiles')
                    ->join(
                        self::PIVOT_TABLE.' as membership',
                        'membership.employment_profile_id',
                        '=',
                        'employment_profiles.id',
                    )
                    ->whereColumn('employment_profiles.user_id', 'quotes.operator_id')
                    ->whereIn('membership.operational_site_id', $this->siteIds);
            });
        });
    }
}

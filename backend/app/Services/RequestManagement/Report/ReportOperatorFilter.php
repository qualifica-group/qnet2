<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use Illuminate\Database\Eloquent\Builder;

/**
 * WHICH GA2 Operatore the report and the dashboard are computed on (spec
 * 0108, D-1): the ONE place that knows how to turn the selected
 * `operator_keys` into SQL. It restricts the CALCULATION, not the rows
 * emitted afterwards — selecting a single operator makes the CSV's TOTALE
 * row and the dashboard's summary tiles that operator's own numbers.
 *
 * `all()` is the default and touches nothing: absent `operator_keys` means
 * every operator, "Non assegnato" included (D-2) — the semantics an
 * ExportRun frozen before this spec relies on, and the reason no existing
 * caller had to change its behaviour.
 *
 * The disjunction is wrapped in its OWN `where()` closure and never hung off
 * the builder's root with a bare `orWhere` (D-7): callers hand this a query
 * that already carries the branch's category match AND
 * RequestManagementScope's visibility rule, and a root-level OR would escape
 * both — widening the perimeter instead of narrowing it. Applied AFTER the
 * scope for the same reason: narrowing an already-scoped query can never
 * reveal a row the actor could not see.
 *
 * Fail-closed by construction: a restricted filter with no ids and without
 * "Non assegnato" compiles to `whereIn(..., [])`, which matches nothing —
 * never to an unfiltered query.
 */
final class ReportOperatorFilter
{
    /** The "Non assegnato" GA2 row (spec 0106 D-13) as a selectable key. */
    public const string UNASSIGNED_KEY = 'unassigned';

    /**
     * @param  array<int, int>  $operatorIds
     */
    private function __construct(
        private readonly bool $unrestricted,
        private readonly array $operatorIds,
        private readonly bool $includeUnassigned,
    ) {}

    public static function all(): self
    {
        return new self(true, [], true);
    }

    /**
     * @param  array<int, string>  $keys  user ids as strings, plus UNASSIGNED_KEY
     */
    public static function fromKeys(array $keys): self
    {
        $ids = array_filter($keys, static fn (string $key): bool => $key !== self::UNASSIGNED_KEY);

        return new self(
            unrestricted: false,
            operatorIds: array_values(array_unique(array_map(intval(...), $ids))),
            includeUnassigned: in_array(self::UNASSIGNED_KEY, $keys, true),
        );
    }

    /**
     * The wire-level rule of D-2, in one place: a MISSING selection is every
     * operator, an EMPTY one is nobody. Validation (`min:1`) keeps the second
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
            $selected->whereIn('quotes.operator_id', $this->operatorIds);

            if ($this->includeUnassigned) {
                $selected->orWhereNull('quotes.operator_id');
            }
        });
    }
}

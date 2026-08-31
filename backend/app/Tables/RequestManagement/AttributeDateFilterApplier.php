<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Date-range filter for `attr.<code>` columns of type `date`/`datetime`
 * (spec 0064 follow-up, post-review; restored on the OFFER's own
 * `quotes.attribute_values` by the user directive 2026-08-31): the contract declares `filterType:
 * "date"` for these two types, so the frontend mounts `agDateColumnFilter`,
 * which sends `{dateFrom, dateTo, type: 'equals'|'inRange'|'lessThan'|
 * 'greaterThan'}` — a shape NEITHER `AppliesTextFilter` (the handler's own
 * filter, correct for spec 0021's custom fields, left untouched) nor
 * `App\Services\Table\FilterApplier::applyDate()` can serve here: the first
 * expects `{filter, type: contains|equals|...}`; the second assumes a REAL
 * DB column `whereDate()` can coerce. `quotes.attribute_values-><code>`
 * is a JSON-extracted STRING, compared lexicographically against bound
 * literals — this class is that comparison, kept entirely in the
 * request-management layer (never reused by the custom-fields subsystem).
 *
 * Stored value shape: `date` -> `Y-m-d`; `datetime` -> `Y-m-d\TH:i` (seconds
 * optionally present — `AttributeValueValidator` accepts both `H:i` and
 * `H:i:s`, and the normalizer never canonicalizes). Every boundary below is
 * built at MINUTE precision, and the upper bound of a day is always the
 * EXCLUSIVE start of the NEXT day — never an inclusive `23:59`/`23:59:59`
 * literal:
 *  - an inclusive `23:59` upper bound would silently EXCLUDE a stored value
 *    that happens to carry seconds past that minute (`23:59:30`);
 *  - an inclusive `00:00:00` lower bound (WITH seconds) would silently
 *    EXCLUDE a stored midnight-exact value with none (`00:00`), since
 *    `'00:00' < '00:00:00'` lexicographically (a prefix-matching shorter
 *    string sorts before the longer one).
 * A minute-precision, EXCLUSIVE-next-day upper bound sidesteps both: any
 * stored value on the target day — whichever precision it happens to carry —
 * always sorts strictly before the next day's `T00:00`.
 *
 * Bound query-builder parameters only, never `whereRaw`; `$jsonColumn` is
 * always built by the caller from the allow-listed `code` of the CURRENTLY
 * scoped category (backend.md §8 / security.md §8).
 */
final class AttributeDateFilterApplier
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function apply(Builder $query, string $jsonColumn, bool $hasTime, array $filter): void
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';
        $from = $this->dateOnly($filter['dateFrom'] ?? null);

        if ($from === null) {
            return;
        }

        $to = $this->dateOnly($filter['dateTo'] ?? null);

        if ($type === 'inRange' && $to !== null) {
            $query->where($jsonColumn, '>=', $this->lowerBound($from, $hasTime))
                ->where($jsonColumn, '<', $this->exclusiveUpperBound($to, $hasTime));

            return;
        }

        match ($type) {
            // "less than $from" — strictly before that day starts, for
            // either granularity.
            'lessThan' => $query->where($jsonColumn, '<', $this->lowerBound($from, $hasTime)),
            // "greater than $from" — the day AFTER $from onward, so $from
            // itself (any time of day) is excluded.
            'greaterThan' => $query->where($jsonColumn, '>=', $this->exclusiveUpperBound($from, $hasTime)),
            // `equals`, and `inRange` with no `dateTo` (mirrors
            // FilterApplier::applyDate's own fallback for the same
            // incomplete payload): the WHOLE day of $from.
            default => $query->where($jsonColumn, '>=', $this->lowerBound($from, $hasTime))
                ->where($jsonColumn, '<', $this->exclusiveUpperBound($from, $hasTime)),
        };
    }

    /**
     * The `Y-m-d` prefix of an AG Grid date filter value (`YYYY-MM-DD` or
     * `YYYY-MM-DD HH:mm:ss`), or null when absent/malformed — a malformed
     * bound adds no constraint rather than throwing (mirrors every other
     * `Applies*Filter` concern's permissive-on-bad-input convention).
     */
    private function dateOnly(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) < 10) {
            return null;
        }

        $prefix = substr($value, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefix) === 1 ? $prefix : null;
    }

    /**
     * Inclusive lower bound for `$dateOnly`'s day.
     */
    private function lowerBound(string $dateOnly, bool $hasTime): string
    {
        return $hasTime ? "{$dateOnly}T00:00" : $dateOnly;
    }

    /**
     * EXCLUSIVE upper bound: the start of the day AFTER `$dateOnly` (see
     * class docblock for why never an inclusive end-of-day literal).
     */
    private function exclusiveUpperBound(string $dateOnly, bool $hasTime): string
    {
        $nextDay = Carbon::parse($dateOnly)->addDay()->format('Y-m-d');

        return $hasTime ? "{$nextDay}T00:00" : $nextDay;
    }
}

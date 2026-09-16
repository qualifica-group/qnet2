<?php

namespace App\Tables\Concerns;

/**
 * The Set Filter's blank entry, shared by every column that resolves its own
 * values or filter (the DERIVED columns: relations, geo, employment, custom
 * fields, attributes).
 *
 * AG Grid renders a `null` among a column's values as its localized blank
 * entry ("(Vuoti)") and sends that same `null` back inside the filter model,
 * so one marker carries both directions and no column needs a sentinel string
 * of its own. The generic engine handles the REAL columns on its own
 * (TableService/FilterApplier); this trait is what keeps the derived ones
 * behaving identically.
 */
trait HandlesBlankSetFilter
{
    /**
     * Whether a set filter payload carries the blank entry — i.e. the user
     * ticked "(Vuoti)" and wants the rows whose cell is empty.
     *
     * @param  array<string, mixed>  $filter
     */
    protected function matchesBlankEntry(array $filter): bool
    {
        $values = $filter['values'] ?? null;

        return is_array($values) && in_array(null, $values, true);
    }

    /**
     * Prepend the blank entry to a resolved value list when the scoped rows
     * hold empty cells. Skipped while a substring search is active: the blank
     * entry matches no search term. `$hasBlanks` stays a closure so its query
     * only runs when it can actually be offered.
     *
     * @param  array<int, string|null>  $values
     * @param  callable(): bool  $hasBlanks
     * @return array<int, string|null>
     */
    protected function withBlankEntry(array $values, ?string $search, callable $hasBlanks): array
    {
        if ($search !== null && $search !== '') {
            return $values;
        }

        if ($hasBlanks()) {
            array_unshift($values, null);
        }

        return $values;
    }
}

<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic distinct-values resolution for the Excel-like set filter's
 * `/values` endpoint (spec 0021), capped at MAX_SET_FILTER_VALUES (mirrors
 * App\Services\Table\FilterApplier). Works uniformly for single-valued
 * (scalar JSON) AND multi-valued (JSON array) fields without needing to know
 * the definition's cardinality: a JSON array comes back from the driver as
 * an encoded string (flattened here); a scalar comes back driver-native.
 */
trait ResolvesDistinctJsonValues
{
    private const int MAX_DISTINCT_VALUES = 500;

    /**
     * @param  Builder<Model>  $query
     * @return array<int, scalar|null>
     */
    public function distinctValues(Builder $query, string $column, string $jsonKey): array
    {
        $raw = (clone $query)
            ->select($this->jsonColumn($column, $jsonKey).' as json_value')
            ->pluck('json_value');

        $values = [];
        $hasBlanks = false;

        foreach ($raw as $item) {
            $flattened = $this->flatten($item);

            if ($flattened === []) {
                // No value stored for this row under that key: it feeds AG
                // Grid's blank entry ("(Vuoti)") instead of a value of its own.
                $hasBlanks = true;

                continue;
            }

            array_push($values, ...$flattened);
        }

        $unique = array_values(array_unique($values, SORT_REGULAR));
        sort($unique);

        $capped = array_slice($unique, 0, self::MAX_DISTINCT_VALUES);

        return $hasBlanks ? array_merge([null], $capped) : $capped;
    }

    /**
     * @return array<int, scalar>
     */
    private function flatten(mixed $item): array
    {
        if ($item === null || $item === '' || $item === '[]') {
            return [];
        }

        // Only a JSON-array-looking string is decoded (a multi-valued
        // field's raw extraction) — a genuine scalar string ("true", "5",
        // "null") must NEVER be reinterpreted as another type.
        if (is_string($item) && str_starts_with($item, '[')) {
            $decoded = json_decode($item, true);

            if (is_array($decoded)) {
                return array_values(array_filter($decoded, static fn (mixed $value): bool => is_scalar($value)));
            }
        }

        return is_scalar($item) ? [$item] : [];
    }
}

<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Set filter for enum/relation fields: matches whether the field is
 * single-valued (scalar equality) or multi-valued (JSON array containment)
 * WITHOUT needing to know which — both branches are OR-combined in one WHERE
 * group, so the SAME `values` payload from AG Grid's Set Filter works either
 * way. Bound parameters only, cardinality capped.
 */
trait AppliesSetFilter
{
    private const int MAX_SET_FILTER_VALUES = 500;

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $column, string $jsonKey, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values) || $values === []) {
            return;
        }

        $clean = array_slice(
            array_values(array_filter($values, static fn (mixed $value): bool => is_scalar($value))),
            0,
            self::MAX_SET_FILTER_VALUES,
        );

        // AG Grid's blank entry ("(Vuoti)") rides along as a null: it selects
        // the rows storing nothing under that key — absent, null, or an empty
        // string/array, the three shapes the value list folds into one entry.
        $matchesBlank = in_array(null, $values, true);

        if ($clean === [] && ! $matchesBlank) {
            return;
        }

        $path = $this->jsonColumn($column, $jsonKey);

        $query->where(function (Builder $group) use ($path, $clean, $matchesBlank): void {
            if ($clean !== []) {
                $group->whereIn($path, $clean);

                foreach ($clean as $value) {
                    $group->orWhereJsonContains($path, $value);
                }
            }

            if ($matchesBlank) {
                $group->orWhereNull($path)
                    ->orWhere($path, '=', '')
                    ->orWhere($path, '=', '[]');
            }
        });
    }
}

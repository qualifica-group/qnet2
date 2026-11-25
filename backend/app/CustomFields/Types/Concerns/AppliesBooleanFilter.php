<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Boolean filter mirrored from App\Services\Table\FilterApplier::applyBoolean.
 *
 * The Set Filter round-trips the values its list was fed, which come back from
 * a JSON column as strings ("true"/"false", "1"/"0"): they are read as
 * booleans here. A `null` is AG Grid's blank entry ("(Vuoti)").
 */
trait AppliesBooleanFilter
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $column, string $jsonKey, array $filter): void
    {
        $values = $this->booleanValues($filter) ?? [];
        $matchesBlank = is_array($filter['values'] ?? null) && in_array(null, $filter['values'], true);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $path = $this->jsonColumn($column, $jsonKey);

        // One `where` per value, never `whereIn`: only `where` compiles a bound
        // boolean against a JSON path as a JSON true/false.
        $query->where(static function (Builder $group) use ($path, $values, $matchesBlank): void {
            foreach ($values as $value) {
                $group->orWhere($path, '=', $value);
            }

            if ($matchesBlank) {
                $group->orWhereNull($path);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, bool>|null
     */
    private function booleanValues(array $filter): ?array
    {
        $values = $filter['values'] ?? null;

        if (is_array($values)) {
            $clean = [];

            foreach ($values as $value) {
                $boolean = $this->toBoolean($value);

                if ($boolean !== null && ! in_array($boolean, $clean, true)) {
                    $clean[] = $boolean;
                }
            }

            return $clean === [] ? null : $clean;
        }

        $single = $this->toBoolean($filter['filter'] ?? ($filter['type'] ?? null));

        return $single === null ? null : [$single];
    }

    private function toBoolean(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 1, $value === '1', $value === 'true' => true,
            $value === 0, $value === '0', $value === 'false' => false,
            default => null,
        };
    }
}

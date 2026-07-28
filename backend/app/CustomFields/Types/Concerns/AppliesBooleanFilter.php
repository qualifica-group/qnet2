<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Boolean filter mirrored from App\Services\Table\FilterApplier::applyBoolean.
 */
trait AppliesBooleanFilter
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $column, string $jsonKey, array $filter): void
    {
        $values = $this->booleanValues($filter);

        if ($values === null || $values === []) {
            return;
        }

        $path = $this->jsonColumn($column, $jsonKey);

        if (count($values) === 1) {
            $query->where($path, '=', $values[0]);

            return;
        }

        $query->whereIn($path, $values);
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
                if (is_bool($value) && ! in_array($value, $clean, true)) {
                    $clean[] = $value;
                }
            }

            return $clean === [] ? null : $clean;
        }

        $single = $filter['filter'] ?? ($filter['type'] ?? null);

        if (is_bool($single)) {
            return [$single];
        }

        if (is_string($single) && in_array($single, ['true', 'false'], true)) {
            return [$single === 'true'];
        }

        return null;
    }
}

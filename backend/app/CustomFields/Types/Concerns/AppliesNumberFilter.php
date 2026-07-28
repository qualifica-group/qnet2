<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Number filter ops mirrored from App\Services\Table\FilterApplier::applyNumber.
 * Bound parameter only.
 */
trait AppliesNumberFilter
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $column, string $jsonKey, array $filter): void
    {
        $path = $this->jsonColumn($column, $jsonKey);
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->numericOrNull($filter['filter'] ?? null);
            $to = $this->numericOrNull($filter['filterTo'] ?? null);

            if ($from !== null && $to !== null) {
                $query->whereBetween($path, [$from, $to]);
            }

            return;
        }

        $value = $this->numericOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return;
        }

        $operator = match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };

        $query->where($path, $operator, $value);
    }

    private function numericOrNull(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }
}

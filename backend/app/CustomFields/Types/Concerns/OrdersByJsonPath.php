<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic ORDER BY on the field's JSON path — identical for every scalar and
 * array-valued type. A multi-valued field (e.g. a multiselect enum) simply
 * sorts by its JSON array's driver-native ordering, an acceptable fallback:
 * AC-016 only requires SCALAR fields to sort correctly.
 */
trait OrdersByJsonPath
{
    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $column, string $jsonKey, string $direction): void
    {
        $query->orderBy($this->jsonColumn($column, $jsonKey), $direction === 'desc' ? 'desc' : 'asc');
    }
}

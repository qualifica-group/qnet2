<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Text filter ops mirrored from App\Services\Table\FilterApplier::applyText,
 * so the SAME AG Grid filter payload shape (`type` + `filter`) behaves
 * identically on a native column and a `custom.<key>` JSON-path column.
 * Bound parameter only; LIKE wildcards escaped.
 */
trait AppliesTextFilter
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $column, string $jsonKey, array $filter): void
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return;
        }

        $value = (string) $value;
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'contains';
        $path = $this->jsonColumn($column, $jsonKey);

        match ($type) {
            'equals' => $query->where($path, '=', $value),
            'notEqual' => $query->where($path, '!=', $value),
            'startsWith' => $query->where($path, 'like', $this->escapeLike($value).'%'),
            'endsWith' => $query->where($path, 'like', '%'.$this->escapeLike($value)),
            'notContains' => $query->where($path, 'not like', '%'.$this->escapeLike($value).'%'),
            default => $query->where($path, 'like', '%'.$this->escapeLike($value).'%'),
        };
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

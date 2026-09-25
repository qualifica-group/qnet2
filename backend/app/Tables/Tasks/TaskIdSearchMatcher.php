<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * The tasks grid's one derived quick-search column (spec 0156, D-1): a
 * purely-numeric search term also matches an exact `id`, OR-combined with
 * the `title` LIKE the generic engine already applies to the other
 * searchable column.
 */
final class TaskIdSearchMatcher
{
    /**
     * `$pattern` arrives already `%…%`-wrapped and LIKE-escaped
     * (TableQueryBuilder::applySearch()); the raw term is safely recovered
     * by stripping the two wrapping `%` — safe because a purely-numeric
     * term carries no character `escapeLike()` would ever have touched.
     *
     * @param  Builder<Task>  $query
     */
    public static function apply(Builder $query, string $pattern): void
    {
        $term = substr($pattern, 1, -1);

        if ($term === '' || ! ctype_digit($term)) {
            return;
        }

        $query->orWhere('tasks.id', '=', (int) $term);
    }
}

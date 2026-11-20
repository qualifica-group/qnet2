<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Set filter and distinct values of the "Gestori account" column (spec 0373,
 * the only Gestore Account column since spec 0406): it reads the whole
 * `quote_user` team, every position.
 *
 * The cell shows each manager's `name`, so both hooks address `users.name`;
 * a request with no manager at all is the blank entry "(Vuoti)".
 */
final class RequestManagerSlotColumn
{
    use HandlesBlankSetFilter;

    /** Caps the WHERE IN cardinality (defence in depth); excess values ignored. */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if ($columnId !== RequestManagerColumns::MANAGERS_COLUMN_ID) {
            return false;
        }

        $values = $this->filterValues($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(function (Builder $group) use ($values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas('managers', static function (Builder $managers) use ($values): void {
                    $managers->whereIn('users.name', $values);
                });
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave('managers');
            }
        });

        return true;
    }

    /**
     * The managers' names among the rows matching $query, plus the blank
     * entry when some of those rows have no manager.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId !== RequestManagerColumns::MANAGERS_COLUMN_ID) {
            return null;
        }

        $names = DB::table('users')
            ->join('quote_user', 'quote_user.user_id', '=', 'users.id')
            ->whereIn('quote_user.quote_id', (clone $query)->select('quotes.id'))
            ->when($search !== null && $search !== '', function (QueryBuilder $builder) use ($search): void {
                $builder->where('users.name', 'like', '%'.$this->escapeLike((string) $search).'%');
            })
            ->distinct()
            ->orderBy('users.name')
            ->limit($limit)
            ->pluck('users.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry(
            $names,
            $search,
            fn (): bool => (clone $query)->whereDoesntHave('managers')->exists(),
        );
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

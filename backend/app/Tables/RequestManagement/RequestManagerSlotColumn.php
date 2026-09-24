<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Support\ManagerPositions;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Sort, set filter and distinct values of the Gestore Account columns that
 * read a `quote_user` pivot POSITION instead of a column of `quotes`: today
 * the GA1 "Tutor" (direttiva utente 2026-09-24). The GA2 "Operatore" is not
 * here: it is denormalized onto `quotes.operator_id`, so RequestRelationColumns
 * handles it as an own FK.
 *
 * The cell shows the occupant's `name`, so every hook addresses `users.name`
 * of the user in that slot; an empty slot is the blank entry "(Vuoti)". The
 * `(quote_id, position)` unique index makes the sort subquery single-row.
 */
final class RequestManagerSlotColumn
{
    use HandlesBlankSetFilter;

    /** Caps the WHERE IN cardinality (defence in depth); excess values ignored. */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * @var array<string, int>
     */
    private const array SLOT_POSITIONS = [
        RequestManagerColumns::GA1_COLUMN_ID => ManagerPositions::GA1,
    ];

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        $position = self::SLOT_POSITIONS[$columnId] ?? null;

        if ($position === null) {
            return false;
        }

        $values = $this->filterValues($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(function (Builder $group) use ($position, $values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas('managers', static function (Builder $managers) use ($position, $values): void {
                    $managers->where('quote_user.position', $position)->whereIn('users.name', $values);
                });
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave('managers', self::inSlot($position));
            }
        });

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $position = self::SLOT_POSITIONS[$columnId] ?? null;

        if ($position === null) {
            return false;
        }

        $query->orderBy(
            $this->slotUsers($position)->select('users.name')->whereColumn('quote_user.quote_id', 'quotes.id')->limit(1),
            $direction,
        );

        return true;
    }

    /**
     * The slot occupants' names among the rows matching $query, plus the
     * blank entry when some of those rows leave the slot empty.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        $position = self::SLOT_POSITIONS[$columnId] ?? null;

        if ($position === null) {
            return null;
        }

        $names = $this->slotUsers($position)
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
            fn (): bool => (clone $query)->whereDoesntHave('managers', self::inSlot($position))->exists(),
        );
    }

    /**
     * Narrows a `managers` relation query to the occupant of one position.
     */
    private static function inSlot(int $position): Closure
    {
        return static function (Builder $managers) use ($position): void {
            $managers->where('quote_user.position', $position);
        };
    }

    private function slotUsers(int $position): QueryBuilder
    {
        return DB::table('users')
            ->join('quote_user', 'quote_user.user_id', '=', 'users.id')
            ->where('quote_user.position', $position);
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

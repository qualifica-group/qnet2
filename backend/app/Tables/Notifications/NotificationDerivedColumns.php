<?php

declare(strict_types=1);

namespace App\Tables\Notifications;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The derived-column machinery for the `notifications` domain (spec 0150),
 * extracted out of NotificationsTableDefinition (file-size split,
 * engineering.md §6) — mirrors WorkOrderDerivedColumns' own split.
 *
 * `status` reads `read_at`'s nullity, never a real column; `title`/
 * `message`/`level` read the JSON `data` column via the query-builder's own
 * `column->path` syntax (never `whereRaw`/`orderByRaw`, backend.md §8) —
 * supported by both MySQL and SQLite (the test suite's connection). Every
 * column id reaching this class comes from NotificationColumnCatalog's own
 * static catalogue, never client input.
 */
final class NotificationDerivedColumns
{
    /** Public column id → the `data->*` JSON path it reads. */
    private const array DATA_COLUMNS = [
        'title' => 'data->title',
        'message' => 'data->message',
        'level' => 'data->level',
    ];

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * `status` (read_at nullity) and the three `data->*` columns.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === 'status') {
            return $this->applyStatusFilter($query, $filter);
        }

        $jsonColumn = self::DATA_COLUMNS[$columnId] ?? null;

        if ($jsonColumn === null) {
            return false;
        }

        $this->filterApplier->apply($query, $jsonColumn, $columnConfig, $filter);

        return true;
    }

    /**
     * `status` sorts by `read_at` itself (NULL/unread sorts as the smallest
     * value on both MySQL and SQLite, so ASC groups unread before read);
     * `title`/`level` sort by their own `data->*` path. `message` never
     * reaches here (not sortable, see the catalogue).
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === 'status') {
            $query->orderBy('read_at', $direction);

            return true;
        }

        $jsonColumn = self::DATA_COLUMNS[$columnId] ?? null;

        if ($jsonColumn === null) {
            return false;
        }

        $query->orderBy($jsonColumn, $direction);

        return true;
    }

    /**
     * Global quick-search (D-5): `title`/`message`, both read from `data->*`.
     *
     * @param  Builder<Model>  $query
     */
    public function applySearch(Builder $query, string $columnId, string $pattern): bool
    {
        $jsonColumn = self::DATA_COLUMNS[$columnId] ?? null;

        if ($jsonColumn === null) {
            return false;
        }

        $query->orWhere($jsonColumn, 'like', $pattern);

        return true;
    }

    /**
     * `status`/`level` are small closed sets: the FULL declared value list
     * (never merely what is present), mirroring WorkOrderDerivedColumns'
     * own enum columns. `title`/`message` declare `hasFilterValues: false`
     * (NotificationColumnCatalog), so this is never reached for them.
     *
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, array $columnConfig, ?string $search): ?array
    {
        return match ($columnId) {
            'status' => $this->filterOptions($search, NotificationColumnCatalog::statusValues()),
            'level' => $this->filterOptions($search, $columnConfig['options'] ?? []),
            default => null,
        };
    }

    /**
     * `values['unread'|'read']` (AG Grid set-filter shape): a single side
     * narrows to it; neither or both leave the query unconstrained.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyStatusFilter(Builder $query, array $filter): bool
    {
        $values = $this->setFilterValues($filter);
        $wantsUnread = in_array('unread', $values, true);
        $wantsRead = in_array('read', $values, true);

        if ($wantsUnread === $wantsRead) {
            return true; // neither selected, or both — no constraint either way.
        }

        $wantsUnread ? $query->whereNull('read_at') : $query->whereNotNull('read_at');

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setFilterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn ($value): bool => is_string($value) && $value !== ''));
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private function filterOptions(?string $search, array $options): array
    {
        if ($search === null || $search === '') {
            return $options;
        }

        return array_values(array_filter($options, static fn (string $option): bool => stripos($option, $search) !== false));
    }
}

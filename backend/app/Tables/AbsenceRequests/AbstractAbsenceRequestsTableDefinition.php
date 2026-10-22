<?php

namespace App\Tables\AbsenceRequests;

use App\Enums\AbsenceDayPart;
use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\Absences\Requests\AbsenceRequestVisibility;
use App\Services\TimeEntries\DailyTargetResolver;
use App\Tables\AbstractTableDefinition;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Shared shape of the three absence request grids (spec 0278): the same
 * columns and the same `viewAny` gate, differing only by the scope each one
 * applies to the query. Every grid starts from the visibility scope, so rows,
 * exports and distinct values are scoped by construction.
 */
abstract class AbstractAbsenceRequestsTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'absences.requests';

    /** Relation columns: id => [relation, column], filtered by a text "contains". */
    private const array TEXT_RELATIONS = [
        'user_name' => ['user', 'name'],
        'absence_type_name' => ['absenceType', 'name'],
        'decided_by_name' => ['decidedBy', 'name'],
    ];

    /** Sortable relation columns: id => [related table, foreign key, column]. */
    private const array SORT_RELATIONS = [
        'user_name' => ['users', 'absence_requests.user_id', 'name'],
        'absence_type_name' => ['absence_types', 'absence_requests.absence_type_id', 'name'],
        'decided_by_name' => ['users', 'absence_requests.decided_by', 'name'],
    ];

    public function __construct(
        protected readonly AbsenceRequestVisibility $visibility,
        private readonly DailyTargetResolver $dailyTarget,
    ) {}

    /**
     * The grid's own narrowing, applied on top of the visibility scope.
     *
     * @param  Builder<AbsenceRequest>  $query
     */
    abstract protected function applyScope(Builder $query, User $actor): void;

    /**
     * @return class-string<AbsenceRequest>
     */
    public function modelClass(): string
    {
        return AbsenceRequest::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives AbsenceRequestPolicy::viewAny from modelClass().

    /**
     * @return Builder<AbsenceRequest>
     */
    public function baseQuery(): Builder
    {
        /** @var User $actor */
        $actor = Auth::user();

        return $this->visibleQuery($actor)->with([
            'user:id,name',
            'user.employment:id,user_id,standard_daily_minutes,break_daily_minutes',
            'absenceType:id,name',
            'decidedBy:id,name',
        ]);
    }

    /**
     * The rows of this grid for an actor, without eager loads: visibility scope plus the grid's own
     * narrowing. The summary counts through it, so a counter always equals the grid.
     *
     * @return Builder<AbsenceRequest>
     */
    public function visibleQuery(User $actor): Builder
    {
        $query = AbsenceRequest::query();
        $this->visibility->scopeToActor($query, $actor);
        $this->applyScope($query, $actor);

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $column = static fn (string $id, string $type, bool $sortable = true, ?string $filter = null, ?array $options = null): array => Catalog::column($id, $type, sortable: $sortable, filter: $filter, options: $options, prefix: self::LABELS);

        return [
            $column('user_name', 'text', filter: 'text'),
            $column('absence_type_name', 'text', filter: 'text'),
            $column('status', 'badge', filter: 'set', options: AbsenceRequestStatus::values()),
            $column('starts_on', 'date', filter: 'date'),
            $column('ends_on', 'date', filter: 'date'),
            $column('is_full_day', 'boolean', filter: 'boolean'),
            $column('day_part', 'text', filter: 'set', options: AbsenceDayPart::values()),
            $column('is_short_notice', 'boolean', filter: 'boolean'),
            $column('minutes', 'number', filter: 'number'),
            $column('submitted_at', 'date', filter: 'date'),
            $column('decided_by_name', 'text', filter: 'text'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filtersFor($this->columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'absence-requests.viewAny'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'status'
            ? array_map(static fn ($meta): array => $meta->toArray(), AbsenceRequestStatus::options())
            : null;
    }

    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'status' ? 'absence_request_status' : null;
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'starts_on', 'direction' => 'desc']];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var AbsenceRequest $row */
        return [
            'id' => $row->id,
            'user_name' => $row->user->name,
            'absence_type_name' => $row->absenceType->name,
            'status' => $row->status->value,
            'starts_on' => $row->starts_on->format('Y-m-d'),
            'ends_on' => $row->ends_on->format('Y-m-d'),
            'is_full_day' => $row->is_full_day,
            'day_part' => $row->day_part?->value,
            'is_short_notice' => $row->is_short_notice,
            'minutes' => $row->minutes,
            'daily_minutes' => $this->dailyTarget->resolve($row->user->employment),
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'decided_by_name' => $row->decidedBy?->name,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return ['view'];
    }

    /**
     * @param  Builder<AbsenceRequest>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (! isset(self::TEXT_RELATIONS[$columnId])) {
            return false;
        }

        Derived::applyText($query, self::TEXT_RELATIONS[$columnId], $filter);

        return true;
    }

    /**
     * @param  Builder<AbsenceRequest>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (! isset(self::SORT_RELATIONS[$columnId])) {
            return false;
        }

        [$table, $foreignKey, $column] = self::SORT_RELATIONS[$columnId];
        Derived::orderByRelated($query, $table, $foreignKey, $column, $direction);

        return true;
    }

    /**
     * @param  Builder<AbsenceRequest>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match (true) {
            $columnId === 'status' => AbsenceRequestStatus::values(),
            $columnId === 'day_part' => AbsenceDayPart::values(),
            isset(self::TEXT_RELATIONS[$columnId]) => Derived::distinctRelated($query, self::TEXT_RELATIONS[$columnId], $search, $limit),
            default => null,
        };
    }
}

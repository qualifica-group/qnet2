<?php

namespace App\Tables\StaffTrainings;

use App\Enums\StaffTrainingOutcome;
use App\Models\StaffTraining;
use App\Models\User;
use App\Services\StaffTrainings\StaffTrainingVisibility;
use App\Tables\AbstractTableDefinition;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `staff-trainings` domain (spec 0284 D-6): the
 * register, scoped by construction to the trainings the actor may see, so
 * rows, exports and distinct values never leave that visibility.
 */
class StaffTrainingsTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'staffTrainings';

    /** Relation columns: id => [relation, column], filtered by a text "contains". */
    private const array TEXT_RELATIONS = [
        'user_name' => ['user', 'name'],
        'course_name' => ['course', 'name'],
    ];

    /** Sortable relation columns: id => [related table, foreign key, column]. */
    private const array SORT_RELATIONS = [
        'user_name' => ['users', 'staff_trainings.user_id', 'name'],
        'course_name' => ['staff_courses', 'staff_trainings.staff_course_id', 'name'],
    ];

    public function __construct(private readonly StaffTrainingVisibility $visibility) {}

    public function domain(): string
    {
        return 'staff-trainings';
    }

    /**
     * @return class-string<StaffTraining>
     */
    public function modelClass(): string
    {
        return StaffTraining::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives StaffTrainingPolicy::viewAny from modelClass().

    /**
     * @return Builder<StaffTraining>
     */
    public function baseQuery(): Builder
    {
        /** @var User $actor */
        $actor = Auth::user();

        $query = StaffTraining::query()->with(['user:id,name', 'course:id,name'])->withExists('attachments as has_attachment');
        $this->visibility->scopeToActor($query, $actor);

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
            $column('course_name', 'text', filter: 'text'),
            $column('completed_on', 'date', filter: 'date'),
            $column('expires_on', 'date', filter: 'date'),
            $column('outcome', 'badge', filter: 'set', options: StaffTrainingOutcome::values()),
            $column('has_attachment', 'boolean', sortable: false),
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
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'staff-trainings.viewAny'],
        ];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'completed_on', 'direction' => 'desc']];
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
        /** @var StaffTraining $row */
        return [
            'id' => $row->id,
            'user_name' => $row->user->name,
            'course_name' => $row->course->name,
            'completed_on' => $row->completed_on->format('Y-m-d'),
            'expires_on' => $row->expires_on?->format('Y-m-d'),
            'outcome' => $row->outcome->value,
            'has_attachment' => (bool) $row->has_attachment,
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
     * @param  Builder<StaffTraining>  $query
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
     * @param  Builder<StaffTraining>  $query
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
     * @param  Builder<StaffTraining>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match (true) {
            $columnId === 'outcome' => StaffTrainingOutcome::values(),
            isset(self::TEXT_RELATIONS[$columnId]) => Derived::distinctRelated($query, self::TEXT_RELATIONS[$columnId], $search, $limit),
            default => null,
        };
    }
}

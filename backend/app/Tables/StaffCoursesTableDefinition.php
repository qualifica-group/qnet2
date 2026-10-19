<?php

namespace App\Tables;

use App\Models\StaffCourse;
use App\Models\User;
use App\Services\StaffCourseService;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `staff-courses` domain (spec 0284 D-2). `jobRoles`
 * is eager-loaded; `trainings_count` is a `withCount` aggregate.
 */
class StaffCoursesTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'staffCourses';

    private const int MINUTES_PER_HOUR = 60;

    public function __construct(private readonly StaffCourseService $service) {}

    public function domain(): string
    {
        return 'staff-courses';
    }

    /**
     * @return class-string<StaffCourse>
     */
    public function modelClass(): string
    {
        return StaffCourse::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives StaffCoursePolicy::viewAny from modelClass().

    /**
     * @return Builder<StaffCourse>
     */
    public function baseQuery(): Builder
    {
        return StaffCourse::query()->with('jobRoles:id,name')->withCount('trainings');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS.'.columns'),
            Catalog::column('duration_hours', 'number', sortable: true, prefix: self::LABELS),
            Catalog::column('validity_months', 'number', sortable: true, filter: 'number', prefix: self::LABELS),
            CatalogColumns::tags('job_roles', self::LABELS.'.columns'),
            Catalog::column('is_active', 'boolean', sortable: true, filter: 'boolean', prefix: self::LABELS),
            CatalogColumns::count('trainings_count', self::LABELS.'.columns'),
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
        return CatalogActions::for('staff-courses');
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'name', 'direction' => 'asc']];
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
        /** @var StaffCourse $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'duration_hours' => $row->duration_minutes === null ? null : round($row->duration_minutes / self::MINUTES_PER_HOUR, 2),
            'validity_months' => $row->validity_months,
            // A `tags` cell takes the names, a string[] (SSRM contract).
            'job_roles' => $row->jobRoles->pluck('name')->all(),
            'is_active' => $row->is_active,
            'trainings_count' => (int) $row->trainings_count,
            'abilities' => [
                'update' => Gate::forUser($actor)->allows('update', $row),
                'delete' => Gate::forUser($actor)->allows('delete', $row),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var StaffCourse $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if ((int) $row->trainings_count === 0 && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * The hours column is stored as minutes: it sorts on `duration_minutes`.
     *
     * @param  Builder<StaffCourse>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'duration_hours') {
            return false;
        }

        $query->orderBy('duration_minutes', $direction);

        return true;
    }

    /**
     * Delegate to StaffCourseService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (422) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var StaffCourse $model */
        $this->service->delete($model);
    }
}

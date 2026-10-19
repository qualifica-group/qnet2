<?php

namespace App\Tables;

use App\Models\JobActivity;
use App\Models\User;
use App\Services\JobActivityService;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `job-activities` domain (spec 0271). `job_roles` is eager-loaded (read-only link, D-5).
 */
class JobActivitiesTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'jobActivities.columns';

    public function __construct(private readonly JobActivityService $service) {}

    public function domain(): string
    {
        return 'job-activities';
    }

    /**
     * @return class-string<JobActivity>
     */
    public function modelClass(): string
    {
        return JobActivity::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives JobActivityPolicy::viewAny from modelClass().

    /**
     * @return Builder<JobActivity>
     */
    public function baseQuery(): Builder
    {
        return JobActivity::query()->with('jobRoles');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS),
            CatalogColumns::description(self::LABELS),
            CatalogColumns::tags('job_roles', self::LABELS),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CatalogColumns::textFilters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CatalogActions::for('job-activities');
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
        /** @var JobActivity $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            // A `tags` cell takes the names, a string[] (SSRM contract).
            'job_roles' => $row->jobRoles->pluck('name')->all(),
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
        /** @var JobActivity $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (! $row->jobRoles->isNotEmpty() && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to JobActivityService::delete() so the generic bulk-delete endpoint
     * respects the SAME guard (409) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var JobActivity $model */
        $this->service->delete($model);
    }
}

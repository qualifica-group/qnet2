<?php

namespace App\Tables;

use App\Models\JobRole;
use App\Models\User;
use App\Services\JobRoleService;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `job-roles` domain (spec 0271). `job_activities` is eager-loaded; `users_count` is a `withCount` aggregate over the linked employment profiles.
 */
class JobRolesTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'jobRoles.columns';

    public function __construct(private readonly JobRoleService $service) {}

    public function domain(): string
    {
        return 'job-roles';
    }

    /**
     * @return class-string<JobRole>
     */
    public function modelClass(): string
    {
        return JobRole::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives JobRolePolicy::viewAny from modelClass().

    /**
     * @return Builder<JobRole>
     */
    public function baseQuery(): Builder
    {
        return JobRole::query()->with('jobActivities')->withCount(['employmentProfiles as users_count']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS),
            CatalogColumns::description(self::LABELS),
            CatalogColumns::tags('job_activities', self::LABELS),
            CatalogColumns::count('users_count', self::LABELS),
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
        return CatalogActions::for('job-roles');
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
        /** @var JobRole $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            // A `tags` cell takes the names, a string[] (SSRM contract).
            'job_activities' => $row->jobActivities->pluck('name')->all(),
            'users_count' => (int) $row->users_count,
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
        /** @var JobRole $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (! $row->users_count > 0 && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to JobRoleService::delete() so the generic bulk-delete endpoint
     * respects the SAME guard (409) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var JobRole $model */
        $this->service->delete($model);
    }
}

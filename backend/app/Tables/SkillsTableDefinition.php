<?php

namespace App\Tables;

use App\Models\Skill;
use App\Models\User;
use App\Services\SkillService;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `skills` domain (spec 0286 D-2). `jobRoles` is
 * eager-loaded; `users_count` is a `withCount` aggregate over the assignments.
 */
class SkillsTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'skills';

    public function __construct(private readonly SkillService $service) {}

    public function domain(): string
    {
        return 'skills';
    }

    /**
     * @return class-string<Skill>
     */
    public function modelClass(): string
    {
        return Skill::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives SkillPolicy::viewAny from modelClass().

    /**
     * @return Builder<Skill>
     */
    public function baseQuery(): Builder
    {
        return Skill::query()->with('jobRoles:id,name')->withCount('userSkills as users_count');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS.'.columns'),
            Catalog::column('category', 'text', sortable: true, filter: 'text', prefix: self::LABELS),
            CatalogColumns::tags('job_roles', self::LABELS.'.columns'),
            Catalog::column('is_active', 'boolean', sortable: true, filter: 'boolean', prefix: self::LABELS),
            CatalogColumns::count('users_count', self::LABELS.'.columns'),
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
        return CatalogActions::for('skills');
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
        /** @var Skill $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'category' => $row->category,
            // A `tags` cell takes the names, a string[] (SSRM contract).
            'job_roles' => $row->jobRoles->pluck('name')->all(),
            'is_active' => $row->is_active,
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
        /** @var Skill $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if ((int) $row->users_count === 0 && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to SkillService::delete() so the generic bulk-delete endpoint
     * respects the SAME guard (422) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var Skill $model */
        $this->service->delete($model);
    }
}

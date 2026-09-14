<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\TaskTemplateService;
use App\Tables\TaskTemplates\TaskTemplateColumnCatalog;
use App\Tables\TaskTemplates\TaskTemplateItemsCountColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `task-templates` domain (spec 0124, AC-008).
 *
 * `name`/`description`/`is_active`/`created_at`/`updated_at` are real DB
 * columns handled entirely by the generic engine. `items_count` is an
 * AGGREGATE column (withCount(), no real DB column); its filter/
 * distinct-values resolution is delegated to TaskTemplateItemsCountColumn.
 */
class TaskTemplatesTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly TaskTemplateService $service,
        private readonly TaskTemplateItemsCountColumn $itemsCountColumn,
    ) {}

    public function domain(): string
    {
        return 'task-templates';
    }

    /**
     * @return class-string<TaskTemplate>
     */
    public function modelClass(): string
    {
        return TaskTemplate::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TaskTemplatePolicy::viewAny
    // from modelClass() (task-templates.viewAny).

    /**
     * @return Builder<TaskTemplate>
     */
    public function baseQuery(): Builder
    {
        return TaskTemplate::query()->withCount('items');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TaskTemplateColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TaskTemplateColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskTemplateColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'name', 'direction' => 'asc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map a TaskTemplate to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var TaskTemplate $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'items_count' => (int) $row->items_count,
            'is_active' => (bool) $row->is_active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via TaskTemplatePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var TaskTemplate $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Handle the aggregate `items_count` number filter (WHERE cannot see a
     * SELECT-list alias). Every other column id (the real columns) falls
     * through to the generic engine.
     *
     * @param  Builder<TaskTemplate>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return match ($columnId) {
            'items_count' => $this->itemsCountColumn->applyDerivedFilter($query, $filter),
            default => false,
        };
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the aggregate
     * `items_count` column.
     *
     * @param  Builder<TaskTemplate>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'items_count' => $this->itemsCountColumn->distinctValues($query, $search, $limit),
            default => null,
        };
    }

    /**
     * Delegate to TaskTemplateService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (spec 0124, D-5) as the single DELETE
     * /task-templates/{taskTemplate} endpoint (AC-011).
     */
    public function deleteModel(Model $model): void
    {
        /** @var TaskTemplate $model */
        $this->service->delete($model);
    }
}

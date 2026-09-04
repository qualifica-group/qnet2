<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\TaskCategory;
use App\Models\User;
use App\Services\TaskCategoryService;
use App\Tables\TaskCategories\TaskCategoryColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `task-categories` domain (spec 0101, D-4).
 *
 * Every column (name, description, color, icon, sort_order, is_active,
 * created_at) is a real DB column handled entirely by the generic engine.
 */
class TaskCategoriesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly TaskCategoryService $service) {}

    public function domain(): string
    {
        return 'task-categories';
    }

    /**
     * @return class-string<TaskCategory>
     */
    public function modelClass(): string
    {
        return TaskCategory::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TaskCategoryPolicy::viewAny
    // from modelClass() (task-categories.viewAny).

    /**
     * @return Builder<TaskCategory>
     */
    public function baseQuery(): Builder
    {
        return TaskCategory::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TaskCategoryColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TaskCategoryColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskCategoryColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
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
     * Map a TaskCategory to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var TaskCategory $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'color' => $row->color,
            'icon' => $row->icon,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via TaskCategoryPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var TaskCategory $row */
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
     * Delegate to TaskCategoryService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (the referenced-by-a-Task guard, D-8b) as the single
     * DELETE /task-categories/{taskCategory} endpoint (AC-041).
     */
    public function deleteModel(Model $model): void
    {
        /** @var TaskCategory $model */
        $this->service->delete($model);
    }
}

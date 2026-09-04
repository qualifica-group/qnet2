<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\TaskType;
use App\Models\User;
use App\Services\TaskTypeService;
use App\Tables\TaskTypes\TaskTypeColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `task-types` domain (spec 0101, D-4).
 *
 * Every column (name, description, color, icon, sort_order, is_active,
 * created_at) is a real DB column handled entirely by the generic engine.
 */
class TaskTypesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly TaskTypeService $service) {}

    public function domain(): string
    {
        return 'task-types';
    }

    /**
     * @return class-string<TaskType>
     */
    public function modelClass(): string
    {
        return TaskType::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TaskTypePolicy::viewAny
    // from modelClass() (task-types.viewAny).

    /**
     * @return Builder<TaskType>
     */
    public function baseQuery(): Builder
    {
        return TaskType::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TaskTypeColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TaskTypeColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskTypeColumnCatalog::actions();
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
     * Map a TaskType to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var TaskType $row */
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
     * Allowed action keys for a single row, via TaskTypePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var TaskType $row */
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
     * Delegate to TaskTypeService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (the referenced-by-a-Task guard, D-8b) as the single
     * DELETE /task-types/{taskType} endpoint (AC-041).
     */
    public function deleteModel(Model $model): void
    {
        /** @var TaskType $model */
        $this->service->delete($model);
    }
}

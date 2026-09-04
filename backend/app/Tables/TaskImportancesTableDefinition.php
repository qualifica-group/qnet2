<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\TaskImportance;
use App\Models\User;
use App\Services\TaskImportanceService;
use App\Tables\TaskImportances\TaskImportanceColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `task-importances` domain (spec 0101, D-4).
 *
 * Every column (name, description, color, icon, sort_order, is_active,
 * created_at) is a real DB column handled entirely by the generic engine.
 */
class TaskImportancesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly TaskImportanceService $service) {}

    public function domain(): string
    {
        return 'task-importances';
    }

    /**
     * @return class-string<TaskImportance>
     */
    public function modelClass(): string
    {
        return TaskImportance::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TaskImportancePolicy::viewAny
    // from modelClass() (task-importances.viewAny).

    /**
     * @return Builder<TaskImportance>
     */
    public function baseQuery(): Builder
    {
        return TaskImportance::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TaskImportanceColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TaskImportanceColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskImportanceColumnCatalog::actions();
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
     * Map a TaskImportance to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var TaskImportance $row */
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
     * Allowed action keys for a single row, via TaskImportancePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var TaskImportance $row */
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
     * Delegate to TaskImportanceService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (the referenced-by-a-Task guard, D-8b) as the single
     * DELETE /task-importances/{taskImportance} endpoint (AC-041).
     */
    public function deleteModel(Model $model): void
    {
        /** @var TaskImportance $model */
        $this->service->delete($model);
    }
}

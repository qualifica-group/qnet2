<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\TaskStatus;
use App\Models\User;
use App\Services\TaskStatusService;
use App\Tables\TaskStatuses\TaskStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `task-statuses` domain (spec 0101, D-4).
 *
 * Every column (name, description, color, icon, sort_order, is_active, completion_percentage,
 * created_at) is a real DB column handled entirely by the generic engine.
 */
class TaskStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly TaskStatusService $service) {}

    public function domain(): string
    {
        return 'task-statuses';
    }

    /**
     * @return class-string<TaskStatus>
     */
    public function modelClass(): string
    {
        return TaskStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TaskStatusPolicy::viewAny
    // from modelClass() (task-statuses.viewAny).

    /**
     * @return Builder<TaskStatus>
     */
    public function baseQuery(): Builder
    {
        return TaskStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TaskStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TaskStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskStatusColumnCatalog::actions();
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
     * Map a TaskStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var TaskStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'color' => $row->color,
            'icon' => $row->icon,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            // spec 0101, D-5/D-6: the six mandatory system rows (the grid
            // hides `delete` on them) and the completion every Task in this
            // status projects.
            'system_key' => $row->system_key,
            'completion_percentage' => $row->completion_percentage,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via TaskStatusPolicy.
     * `delete` is OMITTED for one of the six system rows (D-8c — never
     * deletable); `edit` REMAINS (name/color/icon/completion_percentage are
     * still editable, AC-043).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var TaskStatus $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (! $row->isSystem() && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to TaskStatusService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (system-row protection D-8c, referenced-by-a-Task guard D-8b) as the single
     * DELETE /task-statuses/{taskStatus} endpoint (AC-041).
     */
    public function deleteModel(Model $model): void
    {
        /** @var TaskStatus $model */
        $this->service->delete($model);
    }
}

<?php

namespace App\Tables;

use App\Models\PipelineStatus;
use App\Models\User;
use App\Services\PipelineStatusService;
use App\Tables\PipelineStatuses\PipelineStatusAdvancedFilterCatalog;
use App\Tables\PipelineStatuses\PipelineStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `pipeline-statuses` domain (spec 0023).
 *
 * Every column (name, color, sort_order, group, created_at) is a real DB
 * column handled entirely by the generic engine — `group` (spec 0039 pivot,
 * App\Enums\StatusGroup) replaced the earlier derived `status_group` column
 * (a lookup FK), so it needs no eager-load/derived-column plumbing anymore.
 */
class PipelineStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly PipelineStatusService $service) {}

    public function domain(): string
    {
        return 'pipeline-statuses';
    }

    /**
     * @return class-string<PipelineStatus>
     */
    public function modelClass(): string
    {
        return PipelineStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives PipelineStatusPolicy::viewAny
    // from modelClass() (pipeline-statuses.viewAny).

    /**
     * @return Builder<PipelineStatus>
     */
    public function baseQuery(): Builder
    {
        return PipelineStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return PipelineStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return PipelineStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return PipelineStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return PipelineStatusAdvancedFilterCatalog::advancedFilters();
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
     * Map a PipelineStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var PipelineStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            // spec 0039: the mandatory system rows (D-2) and the fixed
            // 3-value classification (`group`).
            'system_key' => $row->system_key,
            'group' => $row->group->value,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via PipelineStatusPolicy.
     * `delete` is OMITTED for a system row (spec 0039, D-2 — never
     * deletable); `edit` REMAINS (name/color are still editable).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var PipelineStatus $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
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
     * Delegate to PipelineStatusService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (system-row protection spec 0039 D-2,
     * BR-4 referenced-by guard) as the single DELETE
     * /pipeline-statuses/{pipelineStatus} endpoint (AC-003).
     */
    public function deleteModel(Model $model): void
    {
        /** @var PipelineStatus $model */
        $this->service->delete($model);
    }
}

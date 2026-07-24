<?php

namespace App\Tables;

use App\Models\RewardStatus;
use App\Models\User;
use App\Services\RewardStatusService;
use App\Tables\RewardStatuses\RewardStatusAdvancedFilterCatalog;
use App\Tables\RewardStatuses\RewardStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `reward-statuses` domain (spec 0060).
 *
 * Every column (name, description, color, sort_order, is_active,
 * created_at, updated_at) is a real DB column handled entirely by the
 * generic engine.
 */
class RewardStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly RewardStatusService $service) {}

    public function domain(): string
    {
        return 'reward-statuses';
    }

    /**
     * @return class-string<RewardStatus>
     */
    public function modelClass(): string
    {
        return RewardStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives RewardStatusPolicy::viewAny
    // from modelClass() (reward-statuses.viewAny).

    /**
     * @return Builder<RewardStatus>
     */
    public function baseQuery(): Builder
    {
        return RewardStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return RewardStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return RewardStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return RewardStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return RewardStatusAdvancedFilterCatalog::advancedFilters();
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
     * Map a RewardStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var RewardStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            // spec 0060: the mandatory system row (D-2).
            'system_key' => $row->system_key,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via RewardStatusPolicy. `delete`
     * is OMITTED for the system row (spec 0060, D-2 — never deletable);
     * `edit` REMAINS (name/color are still editable).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var RewardStatus $row */
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
     * Delegate to RewardStatusService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (system-row protection spec 0060
     * D-2, BR-4 referenced-by guard) as the single DELETE
     * /reward-statuses/{rewardStatus} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var RewardStatus $model */
        $this->service->delete($model);
    }
}

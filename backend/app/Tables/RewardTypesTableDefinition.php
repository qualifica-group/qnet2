<?php

namespace App\Tables;

use App\Models\RewardType;
use App\Models\User;
use App\Services\RewardTypeService;
use App\Tables\RewardTypes\RewardTypeAdvancedFilterCatalog;
use App\Tables\RewardTypes\RewardTypeColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `reward-types` domain (spec 0058).
 *
 * Every column (name, color, created_at, updated_at) is a real DB column
 * handled entirely by the generic engine — a pure anagraphic, cloned from
 * OpportunityStatusesTableDefinition without the system-row/sort_order/group
 * layer (no row is a protected system row, BR-3).
 */
class RewardTypesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly RewardTypeService $service) {}

    public function domain(): string
    {
        return 'reward-types';
    }

    /**
     * @return class-string<RewardType>
     */
    public function modelClass(): string
    {
        return RewardType::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives RewardTypePolicy::viewAny
    // from modelClass() (reward-types.viewAny).

    /**
     * @return Builder<RewardType>
     */
    public function baseQuery(): Builder
    {
        return RewardType::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return RewardTypeColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return RewardTypeColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return RewardTypeColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return RewardTypeAdvancedFilterCatalog::advancedFilters();
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
     * Map a RewardType to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var RewardType $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via RewardTypePolicy. No system
     * row exists in this domain (BR-3), so no row is excluded from `delete`.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
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
     * Delegate to RewardTypeService::delete() so the generic bulk-delete
     * endpoint respects the same code path as the single DELETE
     * /reward-types/{rewardType} endpoint (BR-3: a plain delete today, the
     * clean insertion point for a future FK guard).
     */
    public function deleteModel(Model $model): void
    {
        /** @var RewardType $model */
        $this->service->delete($model);
    }
}

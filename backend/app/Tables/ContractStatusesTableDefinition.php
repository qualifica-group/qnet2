<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\ContractStatus;
use App\Models\User;
use App\Services\ContractStatusService;
use App\Tables\ContractStatuses\ContractStatusAdvancedFilterCatalog;
use App\Tables\ContractStatuses\ContractStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `contract-statuses` domain (spec 0072).
 *
 * Every column (name, description, color, sort_order, is_active, is_default,
 * group, created_at) is a real DB column handled entirely by the generic
 * engine.
 */
class ContractStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly ContractStatusService $service) {}

    public function domain(): string
    {
        return 'contract-statuses';
    }

    /**
     * @return class-string<ContractStatus>
     */
    public function modelClass(): string
    {
        return ContractStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ContractStatusPolicy::viewAny
    // from modelClass() (contract-statuses.viewAny).

    /**
     * @return Builder<ContractStatus>
     */
    public function baseQuery(): Builder
    {
        return ContractStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ContractStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ContractStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ContractStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return ContractStatusAdvancedFilterCatalog::advancedFilters();
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
     * Map a ContractStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ContractStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'is_default' => $row->is_default,
            // spec 0072: the four mandatory system rows (D-2) and the fixed
            // classification (`group`).
            'system_key' => $row->system_key,
            'group' => $row->group->value,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via ContractStatusPolicy.
     * `delete` is OMITTED for a system row (spec 0072, D-2 — never
     * deletable); `edit` REMAINS (name/color are still editable).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var ContractStatus $row */
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
     * Delegate to ContractStatusService::delete() so the generic
     * bulk-delete endpoint respects the SAME guards (system-row protection
     * spec 0072 D-2, referenced-by guard) as the single DELETE
     * /contract-statuses/{contractStatus} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var ContractStatus $model */
        $this->service->delete($model);
    }
}

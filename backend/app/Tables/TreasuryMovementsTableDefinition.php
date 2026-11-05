<?php

namespace App\Tables;

use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\TreasuryMovement;
use App\Models\User;
use App\Services\Treasury\TreasuryMovementService;
use App\Tables\TreasuryMovements\TreasuryMovementColumnCatalog;
use App\Tables\TreasuryMovements\TreasuryMovementRelationColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `treasury-movements` domain (spec 0355). `inflow` and
 * `outflow` are computed from the type (income and adjustment-in inflow; expense,
 * adjustment-out and the transfer source outflow); the relation columns are derived
 * through an allow-list (TreasuryMovementRelationColumns).
 */
class TreasuryMovementsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly TreasuryMovementService $service) {}

    public function domain(): string
    {
        return 'treasury-movements';
    }

    /**
     * @return class-string<TreasuryMovement>
     */
    public function modelClass(): string
    {
        return TreasuryMovement::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives TreasuryMovementPolicy::viewAny from modelClass().

    /**
     * @return Builder<TreasuryMovement>
     */
    public function baseQuery(): Builder
    {
        return TreasuryMovement::query()->with(TreasuryMovementService::RELATIONS);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TreasuryMovementColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TreasuryMovementColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TreasuryMovementColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'movement_date', 'direction' => 'desc']];
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
        /** @var TreasuryMovement $row */
        $isInflow = $row->type === TreasuryMovementType::Income
            || ($row->type === TreasuryMovementType::Adjustment && $row->direction === TreasuryMovementDirection::In);
        $sub = $row->costType ?? $row->revenueType ?? $row->treasuryType;

        return [
            'id' => $row->id,
            'movement_date' => $row->movement_date->toDateString(),
            'type' => $row->type->value,
            'account' => $row->account->name,
            'counterpart_account' => $row->counterpartAccount?->name,
            'company' => $row->account->company?->denomination,
            'description' => $row->description,
            'sub_account' => $sub === null ? null : $sub->code.' '.$sub->name,
            'registry' => $row->registry?->name,
            'work_order' => $row->workOrder?->code,
            'inflow' => $isInflow ? (string) $row->amount : null,
            'outflow' => $isInflow ? null : (string) $row->amount,
            'created_by' => $row->creator?->name,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        foreach (['view' => 'view', 'delete' => 'delete', 'viewActivity' => 'activity'] as $ability => $key) {
            if (Gate::forUser($actor)->allows($ability, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    public function deleteModel(Model $model): void
    {
        /** @var TreasuryMovement $model */
        $this->service->delete($model);
    }

    /**
     * @param  Builder<TreasuryMovement>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (! TreasuryMovementRelationColumns::handles($columnId)) {
            return false;
        }

        TreasuryMovementRelationColumns::filter($query, $columnId, $filter);

        return true;
    }

    /**
     * @param  Builder<TreasuryMovement>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (! TreasuryMovementRelationColumns::handles($columnId)) {
            return false;
        }

        TreasuryMovementRelationColumns::sort($query, $columnId, $direction);

        return true;
    }

    /**
     * @param  Builder<TreasuryMovement>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return TreasuryMovementRelationColumns::handles($columnId)
            ? TreasuryMovementRelationColumns::distinct($columnId, $query, $search, $limit)
            : null;
    }
}

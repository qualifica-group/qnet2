<?php

namespace App\Tables;

use App\Models\User;
use App\Models\WorkOrderPaymentStatus;
use App\Services\WorkOrderPaymentStatusService;
use App\Tables\WorkOrderPaymentStatuses\WorkOrderPaymentStatusAdvancedFilterCatalog;
use App\Tables\WorkOrderPaymentStatuses\WorkOrderPaymentStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `work-order-payment-statuses` domain (spec 0201).
 *
 * Every column (name, description, color, sort_order, is_active,
 * allows_delivery, created_at, updated_at) is a real DB column handled
 * entirely by the generic engine.
 */
class WorkOrderPaymentStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly WorkOrderPaymentStatusService $service) {}

    public function domain(): string
    {
        return 'work-order-payment-statuses';
    }

    /**
     * @return class-string<WorkOrderPaymentStatus>
     */
    public function modelClass(): string
    {
        return WorkOrderPaymentStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives WorkOrderPaymentStatusPolicy::viewAny
    // from modelClass() (work-order-payment-statuses.viewAny).

    /**
     * @return Builder<WorkOrderPaymentStatus>
     */
    public function baseQuery(): Builder
    {
        return WorkOrderPaymentStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return WorkOrderPaymentStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return WorkOrderPaymentStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return WorkOrderPaymentStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return WorkOrderPaymentStatusAdvancedFilterCatalog::advancedFilters();
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
     * Map a WorkOrderPaymentStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var WorkOrderPaymentStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'allows_delivery' => $row->allows_delivery,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via WorkOrderPaymentStatusPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var WorkOrderPaymentStatus $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
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
     * Delegate to WorkOrderPaymentStatusService::delete() so the generic bulk-delete
     * endpoint respects the SAME behaviour as the single DELETE
     * /work-order-payment-statuses/{paymentMethod} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var WorkOrderPaymentStatus $model */
        $this->service->delete($model);
    }
}

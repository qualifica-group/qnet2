<?php

declare(strict_types=1);

namespace App\Tables;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use App\Services\WorkOrderService;
use App\Tables\WorkOrders\WorkOrderColumnCatalog;
use App\Tables\WorkOrders\WorkOrderDerivedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `work-orders` domain (spec 0093).
 *
 * `code`/`title`/`type`/`callback_date`/`is_force_closed`/`created_at`/
 * `updated_at` are real `work_orders` columns, handled entirely by the
 * generic engine. `contract_number`/`quote` are DERIVED through the `quote`
 * relation (D-2: `quotes.code`/`quotes.title`, never copied) and `status` is
 * the ONE computed column (D-3) — both delegated to WorkOrderDerivedColumns
 * (file-size split, engineering.md §6), so the `status` badge (mapRow) and
 * its `set` filter can never disagree (AC-034).
 */
class WorkOrdersTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly WorkOrderService $service,
        private readonly WorkOrderStatusResolver $statusResolver,
        private readonly WorkOrderDerivedColumns $derivedColumns,
    ) {}

    public function domain(): string
    {
        return 'work-orders';
    }

    /**
     * @return class-string<WorkOrder>
     */
    public function modelClass(): string
    {
        return WorkOrder::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives WorkOrderPolicy::viewAny
    // from modelClass() (work-orders.viewAny).

    /**
     * @return Builder<WorkOrder>
     */
    public function baseQuery(): Builder
    {
        return WorkOrder::query()->with(['quote']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return WorkOrderColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return WorkOrderColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return WorkOrderColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
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
     * Badge metadata for the `type`/`status` columns, driven by
     * WorkOrderType/WorkOrderStatus (mirrors ProductsTableDefinition's own
     * `product_type`).
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return match ($columnId) {
            'type' => array_map(static fn ($meta): array => $meta->toArray(), WorkOrderType::options()),
            'status' => array_map(static fn ($meta): array => $meta->toArray(), WorkOrderStatus::options()),
            default => null,
        };
    }

    /**
     * The `type`/`status` badges are driven by WorkOrderType/WorkOrderStatus,
     * exposed to the frontend config under the `work_order_type`/
     * `work_order_status` enum keys (config/config.php form_enums), so the
     * client localizes the badge label from its own i18n resources instead
     * of the backend-supplied one.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return match ($columnId) {
            'type' => 'work_order_type',
            'status' => 'work_order_status',
            default => null,
        };
    }

    /**
     * Map a WorkOrder to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var WorkOrder $row */
        return [
            'id' => $row->id,
            'code' => $row->code,
            'title' => $row->title,
            'contract_number' => $row->quote?->code,
            'quote' => $row->quote?->title,
            'type' => $row->type?->value,
            'callback_date' => $row->callback_date,
            'is_force_closed' => $row->is_force_closed,
            'status' => $this->statusResolver->resolve($row)->value,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via WorkOrderPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var WorkOrder $row */
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
     * Delegate to WorkOrderService::delete() so the generic bulk-delete
     * endpoint respects the SAME (future) guard as the single DELETE
     * /work-orders/{workOrder} endpoint (D-11, AC-043).
     */
    public function deleteModel(Model $model): void
    {
        /** @var WorkOrder $model */
        $this->service->delete($model);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->derivedColumns->applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->derivedColumns->applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->derivedColumns->distinctValues($columnId, $search, $query, $limit);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->derivedColumns->applySearch($query, $columnId, $pattern);
    }
}

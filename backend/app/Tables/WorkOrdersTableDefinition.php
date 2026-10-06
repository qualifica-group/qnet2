<?php

declare(strict_types=1);

namespace App\Tables;

use App\Enums\ProformaRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use App\Services\WorkOrderService;
use App\Tables\WorkOrders\WorkOrderColumnCatalog;
use App\Tables\WorkOrders\WorkOrderDerivedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `work-orders` domain (spec 0093).
 *
 * `code`/`title`/`type`/`callback_date`/`is_force_closed`/`created_at`/
 * `updated_at` are real `work_orders` columns, handled entirely by the
 * generic engine. `contract_number`/`quote` are DERIVED through the `quote`
 * relation (D-2: `quotes.code`/`quotes.title`, never copied), `supervisors`
 * is a to-many derived through the `work_order_supervisor` pivot (spec 0096,
 * D-7) and `status`/`completion_percentage` are COMPUTED from the root tasks
 * (spec 0149) by WorkOrderStatusResolver — the `status` filter through
 * WorkOrderDerivedColumns (file-size split, engineering.md §6), the
 * completion sort directly — so badge, filter and sort can never disagree.
 *
 * baseQuery() is scoped by WorkOrderVisibilityScope (user directive
 * 2026-09-02): without `work-orders.viewAll` the actor lists only the
 * commesse where they are Responsabile or Partecipante. Rows, exports and
 * distinct filter values all derive from this one query, so they are scoped
 * by construction.
 */
class WorkOrdersTableDefinition extends AbstractTableDefinition
{
    /** Computed from the root tasks (spec 0149), sorted by WorkOrderStatusResolver. */
    private const string COMPLETION_PERCENTAGE_COLUMN = 'completion_percentage';

    /** Spec 0193: the proforma request state, gated by its create permission. */
    private const string PROFORMA_STATUS_COLUMN = 'proforma_status';

    private const string PROFORMA_CREATE_PERMISSION = 'proforma-requests.create';

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
        // `supervisors.avatar` is eager-loaded so the Responsabili cell can
        // render real avatars and not just initials (spec 0096, AC-052),
        // mirroring QuotesTableDefinition's own managers eager load.
        // `participants` carries no column of its own: it is loaded so
        // WorkOrderVisibilityScope::isVisibleTo() answers actionsFor()'s
        // per-row Gate calls in memory instead of querying (user
        // directive 2026-09-02).
        // The root-task aggregates feed `status`/`completion_percentage`
        // (spec 0149, D-8) without one query per row.
        $query = $this->statusResolver->withProgress(WorkOrder::query()->with(['quote', 'supervisors.avatar', 'participants']));

        // Spec 0193, D-11: the proforma state is two EXISTS subqueries, never
        // a query per row, and only computed for actors who may raise requests.
        if ($this->mayRequestProforma(Auth::user())) {
            $query->withExists([
                'proformaRequests as has_proforma_request',
                'proformaRequests as has_pending_proforma_request' => fn (Builder $requests) => $requests->where('status', ProformaRequestStatus::Pending),
            ]);
        }

        return WorkOrderVisibilityScope::scopeToActor($query, Auth::user());
    }

    private function mayRequestProforma(?User $actor): bool
    {
        return $actor?->can(self::PROFORMA_CREATE_PERMISSION) === true;
    }

    /**
     * none | pending (at least one pending) | issued (requests exist, none pending).
     */
    private function proformaStatus(WorkOrder $row): string
    {
        return match (true) {
            (bool) $row->has_pending_proforma_request => ProformaRequestStatus::Pending->value,
            (bool) $row->has_proforma_request => ProformaRequestStatus::Issued->value,
            default => 'none',
        };
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
        $progress = $this->statusResolver->progress($row);

        $proforma = $this->mayRequestProforma($actor) ? [self::PROFORMA_STATUS_COLUMN => $this->proformaStatus($row)] : [];

        return [
            ...$proforma,
            'id' => $row->id,
            'code' => $row->code,
            'title' => $row->title,
            'contract_number' => $row->quote?->code,
            'quote' => $row->quote?->title,
            'type' => $row->type?->value,
            'start_date' => $row->start_date,
            'supervisors' => $row->supervisors->map(fn (User $user): array => $this->userSummary($user))->all(),
            'callback_date' => $row->callback_date,
            'is_force_closed' => $row->is_force_closed,
            'status' => $progress->status->value,
            'completion_percentage' => $progress->completionPercentage,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * A person summary carrying the inline avatar (data URI) so the
     * Responsabili column renders real avatars, not just initials — the same
     * shape and helper QuotesTableDefinition already emits (spec 0096).
     *
     * @return array{id: int, name: string, avatar_url: string|null}
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
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

        $visible = Gate::forUser($actor)->allows('view', $row);

        if ($visible) {
            $allowed[] = 'view';
        }

        // Spec 0193: offered even once issued, so the row shows the yellow,
        // non-clickable state; `proforma_status` drives that on the client.
        if ($visible && $this->mayRequestProforma($actor)) {
            $allowed[] = 'proforma';
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
        if ($columnId === self::COMPLETION_PERCENTAGE_COLUMN) {
            $this->statusResolver->applyCompletionSort($query, $direction);

            return true;
        }

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

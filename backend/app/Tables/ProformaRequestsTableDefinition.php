<?php

namespace App\Tables;

use App\Models\ProformaRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ProformaRequestService;
use App\Tables\ProformaRequests\ProformaRequestColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `proforma-requests` domain (spec 0193). `kind`,
 * `status`, `note` and `created_at` are real columns handled by the generic
 * engine; the relation-backed columns are display only, except
 * `work_order_code`, sortable through a correlated subquery (never a JOIN that
 * would multiply rows). Rows are created from the work order, never from here.
 */
class ProformaRequestsTableDefinition extends AbstractTableDefinition
{
    private const string WORK_ORDER_CODE_COLUMN = 'work_order_code';

    public function __construct(private readonly ProformaRequestService $service) {}

    public function domain(): string
    {
        return 'proforma-requests';
    }

    /**
     * @return class-string<ProformaRequest>
     */
    public function modelClass(): string
    {
        return ProformaRequest::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives ProformaRequestPolicy::viewAny from modelClass().

    /**
     * @return Builder<ProformaRequest>
     */
    public function baseQuery(): Builder
    {
        return ProformaRequest::query()->with([
            'workOrder:id,code,title,quote_id',
            'workOrder.quote:id,company_id',
            'workOrder.quote.company:id,denomination',
            'supplier:id,name',
            'paymentMethod:id,name',
            'assignee:id,name',
            'assigner:id,name',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ProformaRequestColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ProformaRequestColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ProformaRequestColumnCatalog::actions();
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
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ProformaRequest $row */
        $company = $row->workOrder->quote?->company;

        return [
            'id' => $row->id,
            'work_order_code' => $row->workOrder->code,
            'work_order_title' => $row->workOrder->title,
            'company' => $company === null ? null : ['id' => $company->id, 'name' => $company->denomination],
            'kind' => $row->kind->value,
            'supplier' => $row->supplier === null ? null : ['id' => $row->supplier->id, 'name' => $row->supplier->name],
            'payment_method' => $row->paymentMethod === null ? null : ['id' => $row->paymentMethod->id, 'name' => $row->paymentMethod->name],
            'status' => $row->status->value,
            'note' => $row->note,
            'assigned_to' => ['id' => $row->assignee->id, 'name' => $row->assignee->name],
            'assigned_by' => ['id' => $row->assigner->id, 'name' => $row->assigner->name],
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var ProformaRequest $row */
        $gate = Gate::forUser($actor);
        $allowed = [];

        // Action key => policy ability; notes are readable by whoever may view the request.
        $abilities = ['view' => 'view', 'update' => 'update', 'notes' => 'view', 'delete' => 'delete', 'activity' => 'viewActivity'];

        foreach ($abilities as $key => $ability) {
            if ($gate->allows($ability, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Delegate to the Service so the generic bulk-delete endpoint follows the
     * same path as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var ProformaRequest $model */
        $this->service->delete($model);
    }

    /**
     * ORDER BY the work order code via a correlated subquery.
     *
     * @param  Builder<ProformaRequest>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== self::WORK_ORDER_CODE_COLUMN) {
            return false;
        }

        $query->orderBy(
            WorkOrder::query()->select('code')->whereColumn('work_orders.id', 'proforma_requests.work_order_id'),
            $direction,
        );

        return true;
    }
}

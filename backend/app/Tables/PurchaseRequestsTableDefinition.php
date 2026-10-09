<?php

namespace App\Tables;

use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestPriority;
use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestService;
use App\Services\PurchaseRequests\PurchaseRequestVisibilityScope;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `purchase-requests` domain (spec 0208): the RDA list.
 * baseQuery() is scoped by PurchaseRequestVisibilityScope (D-16), so rows,
 * exports and distinct values are scoped by construction. The `line_status`
 * filter (the status tabs) keeps the requests with at least one line in that
 * status; relation columns are filtered/sorted through the allow-listed maps of
 * the column catalogue.
 */
class PurchaseRequestsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly PurchaseRequestService $service) {}

    public function domain(): string
    {
        return 'purchase-requests';
    }

    /**
     * @return class-string<PurchaseRequest>
     */
    public function modelClass(): string
    {
        return PurchaseRequest::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives PurchaseRequestPolicy::viewAny from modelClass().

    /**
     * @return Builder<PurchaseRequest>
     */
    public function baseQuery(): Builder
    {
        $query = PurchaseRequest::query()->with([
            'requester:id,name',
            'functionManager:id,name',
            'customer:id,name',
            'supplier:id,name',
            'workOrder:id,code,title',
            'company:id,denomination',
            'companySite:id,name',
            'operationalSite.addresses.city',
            'businessFunction:id,name',
            'createdBy:id,name',
            'lines:id,purchase_request_id,status',
        ]);

        return PurchaseRequestVisibilityScope::scopeToActor($query, Auth::user());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return Catalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return Catalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'requested_at', 'direction' => 'desc'],
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
        /** @var PurchaseRequest $row */
        $gate = Gate::forUser($actor);

        return [
            'id' => $row->id,
            'subject' => $row->subject,
            'requested_at' => $row->requested_at->toDateString(),
            'priority' => $row->priority->value,
            'requester' => $this->summary($row->requester),
            'function_manager' => $this->summary($row->functionManager),
            'customer' => $this->summary($row->customer),
            'supplier' => $this->summary($row->supplier),
            'work_order' => $row->workOrder === null ? null : ['id' => $row->workOrder->id, 'code' => $row->workOrder->code, 'title' => $row->workOrder->title],
            'company' => ['id' => $row->company->id, 'name' => $row->company->denomination],
            'company_site' => $this->summary($row->companySite),
            'operational_site' => ['id' => $row->operationalSite->id, 'name' => $row->operationalSiteLabel()],
            'business_function' => $this->summary($row->businessFunction),
            'created_by' => $this->summary($row->createdBy),
            'taxable_total' => $row->taxable_total,
            'vat_total' => $row->vat_total,
            'grand_total' => $row->grand_total,
            'status' => $row->status->value,
            'line_status_counts' => $row->lineStatusCounts(),
            'abilities' => [
                'update' => ! $row->isClosed() && $gate->allows('update', $row),
                'delete' => $gate->allows('delete', $row),
                'close' => ! $row->isClosed() && $gate->allows('close', $row),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var PurchaseRequest $row */
        $gate = Gate::forUser($actor);
        $allowed = [];

        // Action key => policy ability.
        $abilities = ['view' => 'view', 'update' => 'update', 'notify_manager' => 'notifyManager', 'close' => 'close', 'delete' => 'delete', 'activity' => 'viewActivity'];
        $openOnly = ['update', 'notify_manager', 'close'];

        foreach ($abilities as $key => $ability) {
            if ($row->isClosed() && in_array($key, $openOnly, true)) {
                continue;
            }

            if ($gate->allows($ability, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Delegate to the Service so the generic bulk-delete follows the same
     * guards as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var PurchaseRequest $model */
        $this->service->delete($model);
    }

    /**
     * @param  Builder<PurchaseRequest>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === Catalog::LINE_STATUS_COLUMN) {
            Derived::applySet($query, 'lines', 'status', PurchaseRequestLineStatus::values(), $filter);

            return true;
        }

        if (! isset(Catalog::TEXT_RELATIONS[$columnId])) {
            return false;
        }

        Derived::applyText($query, Catalog::TEXT_RELATIONS[$columnId], $filter);

        return true;
    }

    /**
     * @param  Builder<PurchaseRequest>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (! isset(Catalog::SORT_RELATIONS[$columnId])) {
            return false;
        }

        [$table, $foreignKey, $column] = Catalog::SORT_RELATIONS[$columnId];
        Derived::orderByRelated($query, $table, $foreignKey, $column, $direction);

        return true;
    }

    /**
     * Quick search over the supplier's VAT number (the other searchable column is real).
     *
     * @param  Builder<PurchaseRequest>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        if ($columnId !== Catalog::SUPPLIER_VAT_COLUMN) {
            return false;
        }

        $query->orWhereHas('supplier.personalData', static fn (Builder $card) => $card->where('vat_number', 'like', $pattern));

        return true;
    }

    /**
     * @param  Builder<PurchaseRequest>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match (true) {
            $columnId === Catalog::LINE_STATUS_COLUMN => PurchaseRequestLineStatus::values(),
            $columnId === 'status' => PurchaseRequestStatus::values(),
            $columnId === 'priority' => PurchaseRequestPriority::values(),
            isset(Catalog::TEXT_RELATIONS[$columnId]) => Derived::distinctRelated($query, Catalog::TEXT_RELATIONS[$columnId], $search, $limit),
            default => null,
        };
    }

    /**
     * @param  object{id: int, name: string}|null  $related
     * @return array{id: int, name: string}|null
     */
    private function summary(?object $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }
}

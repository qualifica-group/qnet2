<?php

namespace App\Tables;

use App\DataObjects\Enums\EnumMeta;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusLabel;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderStatusService;
use App\Support\OperationalSiteLabel;
use App\Tables\PurchaseOrders\PurchaseOrderColumnCatalog as Catalog;
use App\Tables\PurchaseOrders\PurchaseOrderDerivedQuery as OrderQuery;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `purchase-orders` domain (spec 0210): the ODA list.
 * Whoever has `purchase-orders.view` sees every order (D-20), so baseQuery() has
 * no actor scope. The status tabs send a `status` set filter; relation columns
 * are filtered/sorted through the allow-listed maps of the column catalogue.
 */
class PurchaseOrdersTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly PurchaseOrderStatusService $statuses) {}

    public function domain(): string
    {
        return 'purchase-orders';
    }

    /**
     * @return class-string<PurchaseOrder>
     */
    public function modelClass(): string
    {
        return PurchaseOrder::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives PurchaseOrderPolicy::viewAny (`purchase-orders.view`) from modelClass().

    /**
     * @return Builder<PurchaseOrder>
     */
    public function baseQuery(): Builder
    {
        return PurchaseOrder::query()
            ->with([
                'supplier:id,name',
                'supplier.personalData',
                'company:id,denomination',
                'companySite:id,name',
                'operationalSite.addresses.city',
                'paymentMethod:id,name',
                'customer:id,name',
                'workOrder:id,code,title',
                'quote:id,title',
                'opportunity:id,name',
                'sectors:id,name',
                'createdBy:id,name',
            ])
            ->withCount('lines');
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
            ['columnId' => Catalog::CODE_COLUMN, 'direction' => 'desc'],
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
        /** @var PurchaseOrder $row */
        $gate = Gate::forUser($actor);

        return [
            'id' => $row->id,
            'code' => $row->code(),
            'number' => $row->number,
            'year' => $row->year,
            'order_date' => $row->order_date->toDateString(),
            'status' => $row->status->value,
            'supplier' => $this->summary($row->supplier),
            'supplier_vat_number' => $row->supplier->personalData?->vat_number,
            'company' => ['id' => $row->company->id, 'name' => $row->company->denomination],
            'company_site' => $this->summary($row->companySite),
            'operational_site' => $row->operationalSite === null ? null : [
                'id' => $row->operationalSite->id,
                'name' => filled($row->operationalSite->alias) ? (string) $row->operationalSite->alias : OperationalSiteLabel::compose($row->operationalSite->primaryAddress),
            ],
            'description' => $row->description,
            'payment_method' => $this->summary($row->paymentMethod),
            'customer' => $this->summary($row->customer),
            'work_order' => $row->workOrder === null ? null : ['id' => $row->workOrder->id, 'code' => $row->workOrder->code, 'title' => $row->workOrder->title],
            'quote' => $row->quote === null ? null : ['id' => $row->quote->id, 'name' => $row->quote->title],
            'opportunity' => $this->summary($row->opportunity),
            'sectors' => $row->sectors->map(fn (object $sector): array => ['id' => $sector->id, 'name' => $sector->name])->all(),
            'is_internal_user' => $row->is_internal_user,
            'taxable_total' => $row->taxable_total,
            'vat_total' => $row->vat_total,
            'grand_total' => $row->grand_total,
            'lines_count' => (int) $row->lines_count,
            'created_by' => $this->summary($row->createdBy),
            'created_at' => $row->created_at,
            'abilities' => [
                'update' => $row->status->isEditable() && $gate->allows('update', $row),
                'delete' => $row->status->isEditable() && $gate->allows('delete', $row),
                'transitions' => array_map(
                    static fn (PurchaseOrderStatus $status): string => $status->value,
                    $this->statuses->transitionsFor($row, $actor),
                ),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var PurchaseOrder $row */
        $gate = Gate::forUser($actor);
        $allowed = [];

        // Action key => policy ability.
        $abilities = ['view' => 'view', 'update' => 'update', 'pdf' => 'view', 'delete' => 'delete', 'activity' => 'viewActivity'];
        $editableOnly = ['update', 'delete'];

        foreach ($abilities as $key => $ability) {
            if (! $row->status->isEditable() && in_array($key, $editableOnly, true)) {
                continue;
            }

            if ($gate->allows($ability, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Footer totals over the whole filtered set.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return array<string, string>
     */
    public function aggregates(Builder $query): array
    {
        return OrderQuery::totals($query);
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (! isset(Catalog::TEXT_RELATIONS[$columnId])) {
            return false;
        }

        Derived::applyText($query, Catalog::TEXT_RELATIONS[$columnId], $filter);

        return true;
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === Catalog::CODE_COLUMN) {
            OrderQuery::orderByCode($query, $direction);

            return true;
        }

        if ($columnId === 'status') {
            $this->orderByStatusCatalog($query, $direction);

            return true;
        }

        if (! isset(Catalog::SORT_RELATIONS[$columnId])) {
            return false;
        }

        [$table, $foreignKey, $column] = Catalog::SORT_RELATIONS[$columnId];
        Derived::orderByRelated($query, $table, $foreignKey, $column, $direction);

        return true;
    }

    /**
     * The status badges come from the presentation catalogue (spec 0248, D-5):
     * the value stays the enum value (`system_key`), label/color/icon follow the
     * user's edits. Resolved once per column-config build, never per row.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== 'status') {
            return null;
        }

        return PurchaseOrderStatusLabel::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['system_key', 'name', 'color', 'icon'])
            ->map(static fn (PurchaseOrderStatusLabel $label): array => (new EnumMeta($label->system_key, $label->name, $label->color, $label->icon))->toArray())
            ->all();
    }

    /**
     * Orders by the catalogue's `sort_order` through a correlated subquery, so a
     * reordered catalogue reorders the grid with no per-row query.
     *
     * @param  Builder<PurchaseOrder>  $query
     */
    private function orderByStatusCatalog(Builder $query, string $direction): void
    {
        $query->orderBy(
            DB::table('purchase_order_statuses')->select('sort_order')->whereColumn('purchase_order_statuses.system_key', 'purchase_orders.status'),
            $direction,
        );
    }

    /**
     * Quick search over the derived columns: the code, the supplier name and
     * the supplier VAT number (the description is a real column).
     *
     * @param  Builder<PurchaseOrder>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        switch ($columnId) {
            case Catalog::CODE_COLUMN:
                OrderQuery::searchCode($query, $pattern);

                return true;
            case Catalog::SUPPLIER_COLUMN:
                $query->orWhereHas('supplier', static fn (Builder $supplier) => $supplier->where('name', 'like', $pattern));

                return true;
            case Catalog::SUPPLIER_VAT_COLUMN:
                $query->orWhereHas('supplier.personalData', static fn (Builder $card) => $card->where('vat_number', 'like', $pattern));

                return true;
            default:
                return false;
        }
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match (true) {
            $columnId === 'status' => PurchaseOrderStatus::values(),
            $columnId === Catalog::SECTORS_COLUMN => OrderQuery::distinctSectors($query, $search, $limit),
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

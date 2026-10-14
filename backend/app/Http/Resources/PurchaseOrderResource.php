<?php

namespace App\Http\Resources;

use App\Enums\ContactTypeEnum;
use App\Enums\PurchaseOrderStatus;
use App\Models\Contact;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use App\Services\PurchaseOrders\PurchaseOrderLineBalance;
use App\Services\PurchaseOrders\PurchaseOrderLineBillingLedger;
use App\Services\PurchaseOrders\PurchaseOrderStatusService;
use App\Support\OperationalSiteLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a PurchaseOrder (spec 0210 data_contract). Expects the
 * relations loaded by PurchaseOrderService::RESOURCE_RELATIONS plus the
 * `merge_logs_count` aggregate (lazy loading is forbidden). Amounts are decimal
 * strings, dates ISO `Y-m-d`. `abilities` and `field_permissions` derive from
 * the ResourcePermissionsBuilder payload the controller already computed.
 *
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @param  array{resource: array<string, bool>, fields: array<string, array<string, bool>>, actions: array<string, bool>}  $permissions
     */
    public function __construct(PurchaseOrder $resource, private readonly array $permissions)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actions = $this->permissions['actions'];
        // One ledger read for all the lines (no N+1)
        $balances = app(PurchaseOrderLineBillingLedger::class)->forLines($this->lines->modelKeys());

        return [
            'id' => $this->id,
            'number' => $this->number,
            'year' => $this->year,
            'code' => $this->code(),
            'status' => $this->status,
            'supplier' => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'email' => $this->supplierEmail($this->supplier),
                'is_qualified_supplier' => (bool) $this->supplier->is_qualified_supplier,
            ],
            'company' => ['id' => $this->company->id, 'name' => $this->company->denomination],
            'company_site' => ['id' => $this->companySite->id, 'name' => $this->companySite->name],
            'operational_site' => $this->operationalSite === null ? null : [
                'id' => $this->operationalSite->id,
                'name' => filled($this->operationalSite->alias)
                    ? (string) $this->operationalSite->alias
                    : OperationalSiteLabel::compose($this->operationalSite->primaryAddress),
            ],
            'order_date' => $this->order_date->toDateString(),
            'description' => $this->description,
            'general_notes' => $this->general_notes,
            'payment_method' => $this->namedRef($this->paymentMethod),
            'is_internal_user' => $this->is_internal_user,
            'customer' => $this->namedRef($this->customer),
            'work_order' => $this->workOrderRef($this->workOrder),
            'quote' => $this->quote === null ? null : ['id' => $this->quote->id, 'name' => $this->quote->title],
            'opportunity' => $this->namedRef($this->opportunity),
            'sectors' => $this->sectors->map(fn (object $sector): array => ['id' => $sector->id, 'name' => $sector->name])->all(),
            'sector_notes' => $this->sector_notes,
            'taxable_total' => $this->taxable_total,
            'vat_total' => $this->vat_total,
            'grand_total' => $this->grand_total,
            'copy_group_id' => $this->copy_group_id,
            'created_by' => ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
            'lines' => $this->lines->map(fn (PurchaseOrderLine $line): array => $this->lineArray($line, $balances[$line->id]))->all(),
            'merge_logs_count' => (int) $this->merge_logs_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => $this->permissions['resource']['update'],
                'delete' => $actions['delete'],
                'merge' => $actions['merge'],
                'send' => $actions['send'],
                'receive' => $request->user()->can('receive', $this->resource)
                    && ($this->status->isReceivable() || $this->status === PurchaseOrderStatus::Fulfilled),
                'view_activity' => $actions['view_activity'],
                'transitions' => array_map(
                    static fn (PurchaseOrderStatus $status): string => $status->value,
                    app(PurchaseOrderStatusService::class)->transitionsFor($this->resource, $request->user()),
                ),
            ],
            'field_permissions' => $this->permissions['fields'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineArray(PurchaseOrderLine $line, PurchaseOrderLineBalance $balance): array
    {
        return [
            'id' => $line->id,
            'position' => $line->position,
            'product' => $this->namedRef($line->product),
            'description' => $line->description,
            'cost_type' => ['id' => $line->costType->id, 'name' => $line->costType->name, 'color' => $line->costType->color],
            'unit_of_measure' => $line->unitOfMeasure === null ? null : [
                'id' => $line->unitOfMeasure->id,
                'name' => $line->unitOfMeasure->name,
                'symbol' => $line->unitOfMeasure->symbol,
            ],
            'quantity' => $line->quantity,
            'received_quantity' => $balance->received,
            'invoiced_quantity' => $balance->invoicedTotal,
            'invoiced_invoice_quantity' => $balance->invoicedInvoice,
            'residual_quantity' => $balance->residual(),
            'fulfillment' => $balance->fulfillment(),
            'residual_closed' => $line->residual_closed_at === null ? null : [
                'at' => $line->residual_closed_at,
                'by' => $line->residualClosedBy === null ? null : ['id' => $line->residualClosedBy->id, 'name' => $line->residualClosedBy->name],
                'reason' => $line->residual_close_reason,
            ],
            'unit_price' => $line->unit_price,
            'vat_rate' => $line->vatRate === null ? null : [
                'id' => $line->vatRate->id,
                'name' => $line->vatRate->name,
                'rate' => (string) $line->vatRate->rate,
            ],
            'taxable_amount' => $line->taxable_amount,
            'vat_amount' => $line->vat_amount,
            'total_amount' => $line->total_amount,
            'work_order' => $this->workOrderRef($line->workOrder),
            'purchase_request_line' => $line->purchaseRequestLine === null ? null : [
                'id' => $line->purchaseRequestLine->id,
                'purchase_request_id' => $line->purchaseRequestLine->purchase_request_id,
            ],
        ];
    }

    /**
     * The supplier's primary email (else its first one): the default recipient
     * of the order.
     */
    private function supplierEmail(Registry $supplier): ?string
    {
        $emails = $supplier->personalData?->contacts->filter(fn (Contact $contact): bool => $contact->type === ContactTypeEnum::Email);
        $contact = $emails?->first(fn (Contact $contact): bool => $contact->is_primary) ?? $emails?->first();

        return $contact === null ? null : (string) $contact->makeVisible('value')->value;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function namedRef(?object $model): ?array
    {
        return $model === null ? null : ['id' => $model->id, 'name' => $model->name];
    }

    /**
     * @return array{id: int, code: string, title: string|null}|null
     */
    private function workOrderRef(?object $workOrder): ?array
    {
        return $workOrder === null ? null : ['id' => $workOrder->id, 'code' => $workOrder->code, 'title' => $workOrder->title];
    }
}

<?php

namespace App\Http\Resources;

use App\Enums\PurchaseRequestCapability;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Services\PurchaseRequests\PurchaseRequestCapabilityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a PurchaseRequest (spec 0208). Expects the relations loaded by
 * PurchaseRequestService::RESOURCE_RELATIONS (lazy loading is forbidden).
 * Amounts are decimal strings, dates ISO `Y-m-d`. `abilities` and
 * `field_permissions` are derived from the ResourcePermissionsBuilder payload
 * the controller already computed for the `permissions` block.
 *
 * @mixin PurchaseRequest
 */
class PurchaseRequestResource extends JsonResource
{
    /**
     * @param  array{resource: array<string, bool>, fields: array<string, array<string, bool>>, actions: array<string, bool>}  $permissions
     */
    public function __construct(PurchaseRequest $resource, private readonly array $permissions)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actions = $this->permissions['actions'];
        $canUpdate = $this->permissions['resource']['update'];

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'requested_at' => $this->requested_at->toDateString(),
            'priority' => $this->priority,
            'requester' => $this->userSummary($this->requester),
            'function_manager' => $this->userSummary($this->functionManager),
            'customer' => $this->registrySummary($this->customer),
            'supplier' => $this->registrySummary($this->supplier),
            'work_order' => $this->workOrder === null ? null : ['id' => $this->workOrder->id, 'code' => $this->workOrder->code, 'title' => $this->workOrder->title],
            'company' => ['id' => $this->company->id, 'name' => $this->company->denomination],
            'company_site' => ['id' => $this->companySite->id, 'name' => $this->companySite->name],
            'operational_site' => ['id' => $this->operationalSite->id, 'name' => $this->operationalSiteLabel()],
            'business_function' => ['id' => $this->businessFunction->id, 'name' => $this->businessFunction->name],
            'created_by' => $this->userSummary($this->createdBy),
            'notes' => $this->notes,
            'delivery_terms' => $this->delivery_terms,
            'procurement_plan' => $this->procurement_plan,
            'technical_requirements' => $this->technical_requirements,
            'special_conditions' => $this->special_conditions,
            'taxable_total' => $this->taxable_total,
            'vat_total' => $this->vat_total,
            'grand_total' => $this->grand_total,
            'status' => $this->status,
            'closed_by' => $this->closedBy === null ? null : $this->userSummary($this->closedBy),
            'closed_at' => $this->closed_at,
            'close_reason' => $this->close_reason,
            'line_status_counts' => $this->lineStatusCounts(),
            'lines' => $this->lines->map(fn (PurchaseRequestLine $line): array => $this->lineArray($request, $line, $canUpdate))->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => $canUpdate,
                'delete' => $actions['delete'],
                'close' => $actions['close'],
                'notify_manager' => $actions['notify_manager'],
                'view_activity' => $actions['view_activity'],
            ],
            'field_permissions' => $this->permissions['fields'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineArray(Request $request, PurchaseRequestLine $line, bool $canUpdateRequest): array
    {
        $actor = $request->user();
        $open = ! $this->isClosed();

        return [
            'id' => $line->id,
            'position' => $line->position,
            'product' => $line->product === null ? null : ['id' => $line->product->id, 'name' => $line->product->name],
            'description' => $line->description,
            'reason' => $line->reason,
            'unit_of_measure' => $line->unitOfMeasure === null ? null : [
                'id' => $line->unitOfMeasure->id,
                'name' => $line->unitOfMeasure->name,
                'symbol' => $line->unitOfMeasure->symbol,
            ],
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'vat_rate' => $line->vatRate === null ? null : [
                'id' => $line->vatRate->id,
                'name' => $line->vatRate->name,
                'rate' => (string) $line->vatRate->rate,
            ],
            'taxable_amount' => $line->taxable_amount,
            'vat_amount' => $line->vat_amount,
            'total_amount' => $line->total_amount,
            'status' => $line->status,
            'approved_by' => $line->approvedBy === null ? null : $this->userSummary($line->approvedBy),
            'approved_at' => $line->approved_at,
            'oda_reference' => $line->oda_reference,
            'abilities' => [
                'update' => $canUpdateRequest && $line->status === PurchaseRequestLineStatus::PendingApproval,
                'delete' => $open && $actor->can('purchase-requests.deleteLine')
                    && in_array($line->status, [PurchaseRequestLineStatus::PendingApproval, PurchaseRequestLineStatus::Rejected], true),
                'transitions' => array_map(
                    static fn (PurchaseRequestLineStatus $status): string => $status->value,
                    app(PurchaseRequestCapabilityResolver::class)->transitions($actor, $this->resource, $line),
                ),
                'capabilities' => array_map(
                    static fn (PurchaseRequestCapability $capability): string => $capability->value,
                    app(PurchaseRequestCapabilityResolver::class)->effectiveCapabilities($actor, $this->resource),
                ),
            ],
        ];
    }

    /**
     * @param  object{id: int, name: string}  $user
     * @return array{id: int, name: string}
     */
    private function userSummary(object $user): array
    {
        return ['id' => $user->id, 'name' => $user->name];
    }

    /**
     * @param  object{id: int, name: string}|null  $registry
     * @return array{id: int, name: string}|null
     */
    private function registrySummary(?object $registry): ?array
    {
        return $registry === null ? null : ['id' => $registry->id, 'name' => $registry->name];
    }
}

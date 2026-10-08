<?php

namespace App\Http\Resources;

use App\Models\ProformaRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a ProformaRequest (spec 0193). Expects the relations loaded by
 * ProformaRequestService::RESOURCE_RELATIONS (lazy loading is forbidden).
 *
 * @mixin ProformaRequest
 */
class ProformaRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $company = $this->workOrder->quote?->company;
        $customer = $this->workOrder->quote?->opportunity?->registry;

        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'status' => $this->status,
            'issued_at' => $this->issued_at,
            'note' => $this->note,
            'work_order' => ['id' => $this->workOrder->id, 'code' => $this->workOrder->code, 'title' => $this->workOrder->title],
            'company' => $company === null ? null : ['id' => $company->id, 'name' => $company->denomination],
            'customer' => $customer === null ? null : ['id' => $customer->id, 'name' => $customer->name],
            'supplier' => $this->supplier === null ? null : ['id' => $this->supplier->id, 'name' => $this->supplier->name],
            'payment_method' => $this->paymentMethod === null ? null : ['id' => $this->paymentMethod->id, 'name' => $this->paymentMethod->name],
            'invoice' => $this->invoice === null ? null : ['id' => $this->invoice->id, 'number_label' => $this->invoice->number.'/'.$this->invoice->year],
            'assigned_to' => ['id' => $this->assignee->id, 'name' => $this->assignee->name],
            'assigned_by' => ['id' => $this->assigner->id, 'name' => $this->assigner->name],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

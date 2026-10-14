<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a PurchaseOrderReceipt (spec 0254 data_contract). Expects
 * `createdBy:id,name`, `purchaseInvoice:id,document_number` and `lines.purchaseOrderLine:id,position,description`
 * loaded (lazy loading is forbidden). `$canDelete` is decided once per order
 * by the controller: ability `receive` and the order not cancelled.
 *
 * @mixin PurchaseOrderReceipt
 */
class PurchaseOrderReceiptResource extends JsonResource
{
    /** Relations the projection reads. */
    public const array RELATIONS = ['createdBy:id,name', 'purchaseInvoice:id,document_number', 'lines.purchaseOrderLine:id,position,description'];

    public function __construct(PurchaseOrderReceipt $resource, private readonly bool $canDelete)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_date' => $this->receipt_date->toDateString(),
            'delivery_note_number' => $this->delivery_note_number,
            'delivery_note_date' => $this->delivery_note_date?->toDateString(),
            'notes' => $this->notes,
            'created_by' => $this->createdBy === null ? null : ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
            'created_at' => $this->created_at,
            'purchase_invoice' => $this->purchaseInvoice === null ? null : ['id' => $this->purchaseInvoice->id, 'document_number' => $this->purchaseInvoice->document_number],
            'lines' => $this->lines->map(fn (PurchaseOrderReceiptLine $line): array => [
                'id' => $line->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
                'position' => $line->purchaseOrderLine->position,
                'description' => $line->purchaseOrderLine->description,
                'quantity' => $line->quantity,
            ])->all(),
            'abilities' => ['delete' => $this->canDelete],
        ];
    }
}

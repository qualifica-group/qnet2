<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrderEmails;

use App\Http\Requests\WorkOrderEmails\ImportWorkOrderEmailAttachmentRequest;

/**
 * Attachment import for a purchase order email (spec 0210): the shared import
 * payload plus the optional `layout_id` used by the `purchase_order_pdf` source.
 */
class ImportPurchaseOrderEmailAttachmentRequest extends ImportWorkOrderEmailAttachmentRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'layout_id' => ['sometimes', 'nullable', 'integer']];
    }
}

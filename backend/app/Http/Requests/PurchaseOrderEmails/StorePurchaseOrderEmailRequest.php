<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrderEmails;

use App\Http\Requests\WorkOrderEmails\StoreOutboundEmailRequest;

/**
 * POST /api/purchase-orders/{purchaseOrder}/emails (spec 0210): the work order
 * draft payload plus `attach_pdf` (default true), which attaches the generated PDF.
 */
class StorePurchaseOrderEmailRequest extends StoreOutboundEmailRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'attach_pdf' => ['sometimes', 'boolean']];
    }

    public function attachPdf(): bool
    {
        return $this->boolean('attach_pdf', true);
    }
}

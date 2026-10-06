<?php

declare(strict_types=1);

namespace App\Http\Requests\InvoiceEmails;

use App\Http\Requests\WorkOrderEmails\ImportWorkOrderEmailAttachmentRequest;

/**
 * Attachment import for an invoice email (spec 0195, D-12): the shared import
 * payload plus the optional `layout_id` used by the `invoice_pdf` source.
 */
class ImportInvoiceEmailAttachmentRequest extends ImportWorkOrderEmailAttachmentRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'layout_id' => ['sometimes', 'nullable', 'integer']];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderEmails;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for
 * POST /api/work-orders/{workOrder}/emails/{email}/attachments/import (spec
 * 0175, D-7b/c/d). Only the field SHAPE is checked here (which key the
 * chosen `source` requires); whether the referenced ids/bundle are actually
 * reachable by this actor is a 403/422 business rule OutboundEmailAttachmentService
 * resolves, not a plain field validation.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('sendEmail', $workOrder) plus the per-source
 * gate the Service applies).
 */
class ImportWorkOrderEmailAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['documents', 'document_bundle', 'quote_pdf'])],
            'attachment_ids' => ['required_if:source,documents', 'sometimes', 'array', 'min:1'],
            'attachment_ids.*' => ['integer'],
            'document_bundle_id' => ['required_if:source,document_bundle', 'sometimes', 'integer'],
        ];
    }

    public function toData(): ImportAttachmentsData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ImportAttachmentsData::fromValidated($validated);
    }
}

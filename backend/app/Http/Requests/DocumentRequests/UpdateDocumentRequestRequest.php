<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequests;

use App\Models\DocumentRequest;

/**
 * Validates PUT /api/document-requests/{documentRequest} (spec 0428): only the
 * four editable fields. A due date left as it was is not checked against today.
 */
class UpdateDocumentRequestRequest extends SaveDocumentRequestRequest
{
    public function authorize(): bool
    {
        $documentRequest = $this->route('documentRequest');

        return $documentRequest instanceof DocumentRequest && $this->user()->can('update', $documentRequest);
    }

    /**
     * @return array<int, string>
     */
    protected function dueDateRules(): array
    {
        $current = $this->route('documentRequest');
        $unchanged = $current instanceof DocumentRequest
            && $this->input('due_date') === $current->due_date?->toDateString();

        return $unchanged ? ['nullable', 'date_format:Y-m-d'] : parent::dueDateRules();
    }
}

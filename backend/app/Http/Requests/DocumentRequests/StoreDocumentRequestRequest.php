<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequests;

use App\Enums\DocumentChecklistSyncStatus;
use App\Models\DocumentChecklist;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/work-orders/{workOrder}/document-requests (spec 0428, D-8):
 * only active and synced checklists can be requested.
 */
class StoreDocumentRequestRequest extends SaveDocumentRequestRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document_checklist_id' => [
                'required',
                'integer',
                Rule::exists('document_checklists', 'id')
                    ->where('is_active', true)
                    ->where('sync_status', DocumentChecklistSyncStatus::Synced->value)
                    ->whereNull('deleted_at'),
            ],
            ...parent::rules(),
        ];
    }

    public function checklist(): DocumentChecklist
    {
        return DocumentChecklist::query()->findOrFail((int) $this->validated('document_checklist_id'));
    }
}

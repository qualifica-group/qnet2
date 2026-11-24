<?php

declare(strict_types=1);

namespace App\Services\DocumentRequests;

use App\Models\DocumentRequest;

/**
 * Body of the ArchivioSmart PUT /requests/{external_id} (spec 0428, D-4, D-7).
 * Creation carries the project and the checklist; an edit only the four editable
 * fields. `customer_external_id` is never sent.
 */
class DocumentRequestPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(DocumentRequest $request, bool $send): array
    {
        $editable = [
            'due_date' => $request->due_date?->toDateString(),
            'recipient_email' => $request->recipient_email,
            'message' => $request->message,
            'send' => $send,
        ];

        // The remote has never answered: it does not know the request yet.
        if ($request->status !== null) {
            return $editable;
        }

        return [
            'project_external_id' => (string) $request->work_order_id,
            'checklist_code' => $request->checklist_code,
            ...$editable,
        ];
    }
}

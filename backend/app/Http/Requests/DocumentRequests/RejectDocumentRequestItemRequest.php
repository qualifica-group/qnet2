<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequests;

/**
 * Validates POST /api/document-requests/{documentRequest}/items/{item}/reject
 * (spec 0429, D-2): the note is mandatory.
 */
class RejectDocumentRequestItemRequest extends ApproveDocumentRequestItemRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:'.self::NOTE_MAX_LENGTH]];
    }
}

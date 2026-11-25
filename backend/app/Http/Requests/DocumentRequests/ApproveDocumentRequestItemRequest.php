<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequests;

use App\Models\DocumentRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/document-requests/{documentRequest}/items/{item}/approve
 * (spec 0429, D-2): an optional note. Authorization comes first so a user
 * without `document-requests.review` gets 403 before any 422.
 */
class ApproveDocumentRequestItemRequest extends FormRequest
{
    public const int NOTE_MAX_LENGTH = 1000;

    public function authorize(): bool
    {
        $documentRequest = $this->route('documentRequest');

        return $documentRequest instanceof DocumentRequest && (bool) $this->user()?->can('review', $documentRequest);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:'.self::NOTE_MAX_LENGTH]];
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? $note : null;
    }
}

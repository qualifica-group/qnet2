<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequests;

use App\Models\DocumentRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/document-requests/{documentRequest}/revalidate (spec 0432, D-4):
 * the items to validate again with the AI. Authorization comes first, so a user
 * without `document-requests.review` gets 403 before any 422.
 */
class RevalidateDocumentRequestItemsRequest extends FormRequest
{
    /** Upper bound of one call: every item is one AI validation. */
    public const int MAX_ITEMS = 100;

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
        return [
            'item_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'item_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['item_ids' => __('archivio_smart.attributes.item_ids')];
    }

    /**
     * @return array<int, int>
     */
    public function itemIds(): array
    {
        return array_map('intval', (array) $this->validated('item_ids'));
    }
}

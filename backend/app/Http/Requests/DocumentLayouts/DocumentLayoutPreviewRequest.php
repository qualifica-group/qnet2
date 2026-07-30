<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentLayouts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the body of POST /api/document-layouts/{documentLayout}/preview
 * (spec 0070): the only input is an OPTIONAL `quote_id`, deliberately NOT
 * validated with `exists:quotes,id` — an unknown id must 404 (via the
 * controller's own `Quote::findOrFail`), not fall through as a generic 422.
 *
 * Authorization is intentionally NOT handled here: `document-layouts.view` on
 * the bound layout and `quotes.view` on the resolved Quote (when `quote_id`
 * is given) are both enforced by the controller.
 */
class DocumentLayoutPreviewRequest extends FormRequest
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
            'quote_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function quoteId(): ?int
    {
        $value = $this->validated('quote_id');

        return $value === null ? null : (int) $value;
    }
}

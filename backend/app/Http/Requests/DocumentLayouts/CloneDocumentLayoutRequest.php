<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentLayouts;

use App\DataObjects\DocumentLayouts\CloneDocumentLayoutData;
use App\Models\DocumentLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/document-layouts/{documentLayout}/clone
 * (spec 0339): the copy's `name` (unique within the SOURCE's module, the copy
 * never changes module) and `code` (unique globally, same shape as on create).
 * Authorization stays in the controller (view on the source + create).
 */
class CloneDocumentLayoutRequest extends FormRequest
{
    private const int NAME_MAX = 191;

    private const int CODE_MAX = 64;

    public function authorize(): bool
    {
        // Authorization handled in the controller via DocumentLayoutPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var DocumentLayout $source */
        $source = $this->route('documentLayout');

        return [
            'name' => [
                'required', 'string', 'max:'.self::NAME_MAX,
                Rule::unique('document_layouts', 'name')->where('module', $source->module->value),
            ],
            'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('document_layouts', 'code')],
        ];
    }

    public function toData(): CloneDocumentLayoutData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CloneDocumentLayoutData::fromValidated($validated);
    }
}

<?php

namespace App\Http\Requests\Merge;

use App\Services\Merge\MergeDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /{registries|referents}/merge/preview (spec 0394). The entity
 * comes from the route default; authorization stays in the controller.
 */
class MergePreviewRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:2', 'max:'.MergeDefinition::MAX_RECORDS],
            'ids.*' => ['integer', 'distinct', Rule::exists((string) $this->route('entity'), 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map('intval', $this->validated('ids'));
    }
}

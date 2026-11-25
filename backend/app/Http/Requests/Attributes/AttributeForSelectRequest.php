<?php

declare(strict_types=1);

namespace App\Http\Requests\Attributes;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/attributes/for-select (ADR 0011, spec
 * 0429 D-7): the "Campo attuale" picker. `exclude_id` leaves out the field
 * being edited. No gate beyond `auth:sanctum` (ADR 0011).
 */
class AttributeForSelectRequest extends FormRequest
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
        $maxLimit = BaseApiController::MAX_LIMIT;

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'exclude_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }

    public function excludeId(): ?int
    {
        $excludeId = $this->validated('exclude_id');

        return $excludeId === null ? null : (int) $excludeId;
    }
}

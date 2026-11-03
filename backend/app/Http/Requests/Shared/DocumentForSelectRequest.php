<?php

namespace App\Http\Requests\Shared;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query validation shared by the document for-select endpoints (invoices,
 * purchase invoices/orders/requests, contracts; ADR 0011, spec 0347). No gate
 * beyond `auth:sanctum` (ADR 0011, amended 2026-07-31): the rows are narrowed
 * by each module's own scope in its Service.
 */
class DocumentForSelectRequest extends FormRequest
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
            'include_total' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}

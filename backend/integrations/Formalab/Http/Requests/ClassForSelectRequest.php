<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/integrations/formalab/classes/for-select (ADR 0011, spec 0426 D-4).
 * No authorization beyond auth:sanctum: the list feeds the work order class
 * select of whoever edits the work order.
 */
class ClassForSelectRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.BaseApiController::MAX_LIMIT],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}

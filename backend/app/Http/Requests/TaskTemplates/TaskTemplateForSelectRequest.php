<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskTemplates;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/task-templates/for-select (ADR 0011,
 * spec 0124).
 *
 * No permission gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31).
 * Pagination bounds mirror BaseApiController::validateRequest (offset >= 0,
 * 1 <= limit <= MAX_LIMIT).
 */
class TaskTemplateForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the TaskTemplatePolicy.
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
        ];
    }

    /**
     * The validated query as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}

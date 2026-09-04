<?php

namespace App\Http\Requests\Tasks;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/tasks/for-select (ADR 0011, spec 0101),
 * mirroring ReferentForSelectRequest. Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 *
 * `exclude_id` is NOT part of the shared ForSelectQuery DTO — it serves the
 * Task form's own parent picker (AC-082: a Task is never offered as its own
 * parent), so it is read directly off the request and passed to the Service
 * as a separate argument, exactly as ReferentForSelectRequest does with
 * `registry_id`. Adding it to the shared DTO would push a single module's
 * concern onto every other for-select consumer.
 *
 * No permission gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31);
 * the rows are still restricted by TaskVisibilityScope inside the Service.
 */
class TaskForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No resource gate on a for-select endpoint (ADR 0011).
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
            'exclude_id' => ['sometimes', 'integer'],
        ];
    }

    /**
     * The validated id to drop from the list, or null when not submitted.
     */
    public function excludeId(): ?int
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_key_exists('exclude_id', $validated) ? (int) $validated['exclude_id'] : null;
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

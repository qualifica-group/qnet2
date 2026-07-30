<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentLayouts;

use App\DataObjects\Shared\ForSelectQuery;
use App\Enums\DocumentLayoutModule;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/document-layouts/for-select (ADR 0011,
 * spec 0069).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', DocumentLayout::class)). `module` is REQUIRED
 * (AC-083, unlike every other for-select consumer): a document layout only
 * ever makes sense scoped to one module, so it is resolved here as its own
 * typed value via module() rather than folded into the shared ForSelectQuery
 * DTO (which every OTHER for-select consumer uses unmodified). Pagination
 * bounds mirror BaseApiController::validateRequest (offset >= 0, 1 <= limit
 * <= MAX_LIMIT).
 */
class DocumentLayoutForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the DocumentLayoutPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxLimit = BaseApiController::MAX_LIMIT;

        return [
            'module' => ['required', Rule::enum(DocumentLayoutModule::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * The validated `module`, resolved to its enum case.
     */
    public function module(): DocumentLayoutModule
    {
        return DocumentLayoutModule::from((string) $this->validated('module'));
    }

    /**
     * The validated query (minus `module`) as a typed DTO — no magic array
     * crosses into the Service (see standards/architecture.md → Data
     * Transfer Objects).
     */
    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}

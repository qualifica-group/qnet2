<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentBundles;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/document-bundles/for-select (ADR 0011,
 * spec 0175, D-14).
 *
 * Authorization is intentionally NOT handled here: NO gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31) — the composer's "Da modello
 * documenti" picker needs this list from an actor who may legitimately lack
 * browse rights on the "Modelli documenti" configurator itself.
 */
class DocumentBundleForSelectRequest extends FormRequest
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

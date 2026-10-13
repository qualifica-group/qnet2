<?php

namespace App\Http\Requests\Geo;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/provinces/for-select (ADR 0011), mirroring
 * StateForSelectRequest.
 *
 * Unlike GET /api/provinces (ListProvincesRequest), `state_id` is NOT required:
 * this is a free-search for-select feeding a RELATION field that targets
 * provinces (spec 0225), not the address cascade. No authorization beyond
 * auth:sanctum — provinces are read-only reference data with no per-resource
 * permission (see GeoController's documented exception).
 */
class ProvinceForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reference lookup: gated only by auth:sanctum, no per-resource ability.
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

    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}

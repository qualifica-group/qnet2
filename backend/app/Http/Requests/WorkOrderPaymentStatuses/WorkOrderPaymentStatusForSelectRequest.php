<?php

namespace App\Http\Requests\WorkOrderPaymentStatuses;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/work-order-payment-statuses/for-select
 * (ADR 0011, spec 0201). No permission gate beyond `auth:sanctum` (ADR 0011):
 * the option list feeds the line-payment editor. Pagination bounds mirror
 * BaseApiController::validateRequest.
 */
class WorkOrderPaymentStatusForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the WorkOrderPaymentStatusPolicy.
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

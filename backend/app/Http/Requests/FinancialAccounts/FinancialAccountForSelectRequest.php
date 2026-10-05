<?php

namespace App\Http\Requests\FinancialAccounts;

use App\DataObjects\Shared\ForSelectQuery;
use App\Enums\FinancialAccountType;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/financial-accounts/for-select (ADR 0011,
 * spec 0189): the standard search/pagination/hydration plus the optional
 * `type` filter (e.g. only bank accounts for the card's "associate to" picker).
 */
class FinancialAccountForSelectRequest extends FormRequest
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
            'type' => ['sometimes', 'string', Rule::enum(FinancialAccountType::class)],
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

    public function accountType(): ?FinancialAccountType
    {
        return FinancialAccountType::tryFrom((string) $this->validated('type'));
    }
}

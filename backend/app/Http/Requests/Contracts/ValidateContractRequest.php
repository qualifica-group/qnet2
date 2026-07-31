<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\ValidateContractData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contracts/{contract}/validate (spec
 * 0072, BR-3). Both fields optional: `validated_at` defaults to today in the
 * Service (AC-009); a submitted `contract_status_id` must reference an
 * ACTIVE status (same rule as UpdateContractRequest's).
 *
 * Authorization stays in the controller (ContractPolicy::validate).
 */
class ValidateContractRequest extends FormRequest
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
            // AC-017: never a future date.
            'validated_at' => ['sometimes', 'date', 'before_or_equal:today'],
            'contract_status_id' => [
                'sometimes',
                'integer',
                Rule::exists('contract_statuses', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function toData(): ValidateContractData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ValidateContractData::fromValidated($validated);
    }
}

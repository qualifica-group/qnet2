<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\ValidateContractData;
use App\Enums\ContractStatusGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contracts/{contract}/validate (spec
 * 0072, BR-3). Both fields optional: `validated_at` defaults to today in the
 * Service (AC-009) and `contract_status_id` to the system "Validato" row
 * (user directive 2026-08-31); a submitted `contract_status_id` must
 * reference an ACTIVE status belonging to the `closed_won` group in ONE
 * combined rule — the mirror image of TerminateContractRequest's
 * closed_lost rule, so validating can never leave the contract outside a
 * positive-outcome status.
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
                Rule::exists('contract_statuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->where('group', ContractStatusGroup::ClosedWon->value)
                ),
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

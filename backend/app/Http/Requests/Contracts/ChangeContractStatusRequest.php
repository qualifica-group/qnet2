<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\ChangeContractStatusData;
use App\Enums\ContractStatusGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contracts/{contract}/change-status
 * (user directive 2026-08-31 rev.2). `contract_status_id` is mandatory and
 * must reference an ACTIVE status of the `open` or `pending` group in ONE
 * combined rule — the same shape TerminateContractRequest uses for
 * `closed_lost` and ValidateContractRequest for `closed_won`.
 *
 * Authorization stays in the controller (ContractPolicy::changeStatus).
 */
class ChangeContractStatusRequest extends FormRequest
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
            'contract_status_id' => [
                'required',
                'integer',
                Rule::exists('contract_statuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereIn('group', [
                        ContractStatusGroup::Open->value,
                        ContractStatusGroup::Pending->value,
                    ])
                ),
            ],
        ];
    }

    public function toData(): ChangeContractStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ChangeContractStatusData::fromValidated($validated);
    }
}

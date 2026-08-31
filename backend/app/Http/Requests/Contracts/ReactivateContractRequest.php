<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\ReactivateContractData;
use App\Enums\ContractStatusGroup;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contracts/{contract}/reactivate (spec
 * 0072, BR-2, extended by the user directive of 2026-08-31).
 *
 * `contract_status_id` is REQUIRED when the contract is disdetto — nothing
 * ever recorded the status it sat on before the disdetta, so the destination
 * comes from the dialog — and irrelevant on the suspended path, which
 * restores the pre-suspension status by itself (D-3). Whatever the path, a
 * submitted status must be ACTIVE and must belong to the `open`/`pending`
 * groups (directive 2026-08-31 rev.2): a reactivated contract goes back to
 * the working phase, never onto another closure.
 *
 * Authorization stays in the controller (ContractPolicy::reactivate).
 */
class ReactivateContractRequest extends FormRequest
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
        $presence = $this->currentContract()->terminated_at !== null ? 'required' : 'sometimes';

        return [
            'contract_status_id' => [
                $presence,
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

    public function toData(): ReactivateContractData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ReactivateContractData::fromValidated($validated);
    }

    private function currentContract(): Contract
    {
        /** @var Contract $contract */
        $contract = $this->route('contract');

        return $contract;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\TerminateContractData;
use App\Enums\ContractStatusGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contracts/{contract}/terminate (spec
 * 0072, BR-4). `terminated_at`/`termination_reason` are mandatory;
 * `contract_status_id` is optional but, when submitted, must reference an
 * ACTIVE status belonging to the `closed_lost` group in ONE combined rule
 * (AC-015) — a status that merely exists/is active but belongs to another
 * group is rejected here, before the Service ever runs.
 *
 * Authorization stays in the controller (ContractPolicy::terminate).
 */
class TerminateContractRequest extends FormRequest
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
            'terminated_at' => ['required', 'date', 'before_or_equal:today'],
            'termination_reason' => ['required', 'string', 'max:2000'],
            'contract_status_id' => [
                'sometimes',
                'integer',
                Rule::exists('contract_statuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->where('group', ContractStatusGroup::ClosedLost->value)
                ),
            ],
        ];
    }

    public function toData(): TerminateContractData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TerminateContractData::fromValidated($validated);
    }
}

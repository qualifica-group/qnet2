<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\ScheduleContractData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contracts/{contract}/schedule (spec
 * 0072). `expiry_date`/`contract_status_id` are mandatory; `renewal_date` is
 * optional and, when present, must not be later than `expiry_date`
 * (AC-013). Deliberately NOT constrained to a past-or-today date (unlike
 * validate/terminate): scheduling a contract's renewal/expiry is precisely
 * about setting a FUTURE date.
 *
 * Authorization stays in the controller (ContractPolicy::schedule).
 */
class ScheduleContractRequest extends FormRequest
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
            'expiry_date' => ['required', 'date'],
            'renewal_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:expiry_date'],
            'contract_status_id' => [
                'required',
                'integer',
                Rule::exists('contract_statuses', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function toData(): ScheduleContractData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ScheduleContractData::fromValidated($validated);
    }
}

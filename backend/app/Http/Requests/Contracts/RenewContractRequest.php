<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/contracts/{contract}/renewals (spec 0358, D-12). The new expiry
 * must be strictly after the current one when present; the renewal date
 * cannot follow the expiry (same invariant as UpdateContractRequest).
 * Authorization stays in the controller (ContractPolicy::program).
 */
class RenewContractRequest extends FormRequest
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
        /** @var Contract $contract */
        $contract = $this->route('contract');

        $expiry = ['required', 'date_format:Y-m-d'];
        if ($contract->expiry_date !== null) {
            $expiry[] = 'after:'.$contract->expiry_date->toDateString();
        }

        return [
            'expiry_date' => $expiry,
            'renewal_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:expiry_date'],
        ];
    }
}

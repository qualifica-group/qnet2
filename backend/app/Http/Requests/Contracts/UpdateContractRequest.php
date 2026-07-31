<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\UpdateContractData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Contract;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/contracts/{contract} (spec 0072,
 * MT-02). Every field is `sometimes` (partial PATCH). `contract_status_id`,
 * when submitted, must reference an ACTIVE status — the `restrictOnDelete`
 * FK only guarantees existence, not activeness (data_contract: "422 stato
 * inesistente/non attivo").
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $contract)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $contract (AC-039).
 */
class UpdateContractRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ContractPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contract_status_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('contract_statuses', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'renewal_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'payment_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'comments' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'contracts';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->currentContract();
    }

    private function currentContract(): Contract
    {
        /** @var Contract $contract */
        $contract = $this->route('contract');

        return $contract;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateContractData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateContractData::fromValidated($validated);
    }
}

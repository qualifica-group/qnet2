<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractStatuses;

use App\DataObjects\ContractStatuses\CreateContractStatusData;
use App\Enums\ContractStatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contract-statuses (spec 0072).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', ContractStatus::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). `name` is unique. `color`/`group` are
 * REQUIRED — every row carries a color and a classification
 * (App\Enums\ContractStatusGroup). `sort_order`/`system_key` are NOT accepted
 * here (absent from rules() -> validated() silently drops them) —
 * server-managed, see App\Services\Statuses\StatusOrderManager. `is_default`
 * is resolved through App\Services\Contracts\ContractStatusDefaultManager
 * (BR-5), never mass-assigned directly.
 */
class StoreContractStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ContractStatusPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('contract_statuses', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['required', 'string', 'max:32'],
            'group' => ['required', 'string', Rule::enum(ContractStatusGroup::class)],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
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
        return 'contract-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateContractStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateContractStatusData::fromValidated($validated);
    }
}

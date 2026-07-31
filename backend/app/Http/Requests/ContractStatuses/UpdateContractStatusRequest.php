<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractStatuses;

use App\DataObjects\ContractStatuses\UpdateContractStatusData;
use App\Enums\ContractStatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\ContractStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/contract-statuses/{contractStatus}
 * (spec 0072). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $contractStatus)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model. `name` is unique ignoring self. `color`, when
 * submitted, cannot be null/empty: `sometimes|required`.
 * `sort_order`/`system_key` are NOT accepted here (see
 * App\Services\Statuses\StatusOrderManager); on a system row,
 * `description`/`group`/`is_active`/`is_default` are rejected at the Service
 * layer (App\Services\Statuses\SystemStatusGuard). `is_default`'s BR-5
 * transition rules are NOT enforced here: they need the model's CURRENT
 * `is_default`/`is_active` inside the same transaction as the write, so they
 * live in App\Services\Contracts\ContractStatusDefaultManager, called by
 * ContractStatusService::update().
 */
class UpdateContractStatusRequest extends FormRequest
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
        /** @var ContractStatus $contractStatus */
        $contractStatus = $this->route('contractStatus');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('contract_statuses', 'name')->ignore($contractStatus->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
            'group' => ['sometimes', 'string', Rule::enum(ContractStatusGroup::class)],
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
        /** @var ContractStatus $contractStatus */
        $contractStatus = $this->route('contractStatus');

        return $contractStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateContractStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateContractStatusData::fromValidated($validated);
    }
}

<?php

namespace App\Http\Requests\RewardStatuses;

use App\DataObjects\RewardStatuses\CreateRewardStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/reward-statuses (spec 0060).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', RewardStatus::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). `name` is unique (BR-1). `color` is
 * REQUIRED (D-4, BR-2). `sort_order`/`system_key` are not accepted here
 * (absent from rules() -> validated() silently drops them, "unknown field
 * ignorato") — server-managed, see App\Services\Statuses\StatusOrderManager.
 */
class StoreRewardStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via RewardStatusPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('reward_statuses', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['required', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
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
        return 'reward-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateRewardStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateRewardStatusData::fromValidated($validated);
    }
}

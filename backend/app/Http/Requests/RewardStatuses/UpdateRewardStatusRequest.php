<?php

namespace App\Http\Requests\RewardStatuses;

use App\DataObjects\RewardStatuses\UpdateRewardStatusData;
use App\Enums\RewardStatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\RewardStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/reward-statuses/{rewardStatus}
 * (spec 0060). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $rewardStatus)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model. `name` is unique ignoring self (BR-1). `color`, when
 * submitted, cannot be null/empty (D-4, BR-2): `sometimes|required`.
 * `group`, when submitted, must be a valid App\Enums\RewardStatusGroup value
 * (spec 0073). `sort_order`/`system_key` are not accepted here (see
 * App\Services\Statuses\StatusOrderManager); on a system row,
 * `group`/`description`/`is_active` are rejected at the Service layer
 * (App\Services\Statuses\SystemStatusGuard, BR-3).
 */
class UpdateRewardStatusRequest extends FormRequest
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
        /** @var RewardStatus $rewardStatus */
        $rewardStatus = $this->route('rewardStatus');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('reward_statuses', 'name')->ignore($rewardStatus->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
            'group' => ['sometimes', 'required', Rule::enum(RewardStatusGroup::class)],
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
        /** @var RewardStatus $rewardStatus */
        $rewardStatus = $this->route('rewardStatus');

        return $rewardStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateRewardStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateRewardStatusData::fromValidated($validated);
    }
}

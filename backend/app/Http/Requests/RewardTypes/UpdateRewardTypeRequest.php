<?php

namespace App\Http\Requests\RewardTypes;

use App\DataObjects\RewardTypes\UpdateRewardTypeData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\RewardType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/reward-types/{rewardType}
 * (spec 0058). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $rewardType)). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit on this
 * specific model. `name` is unique ignoring self (BR-1). `color`, when
 * submitted, cannot be null/empty (BR-2, D-5): `sometimes|required`.
 */
class UpdateRewardTypeRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via RewardTypePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var RewardType $rewardType */
        $rewardType = $this->route('rewardType');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('reward_types', 'name')->ignore($rewardType->id)],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
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
        return 'reward-types';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var RewardType $rewardType */
        $rewardType = $this->route('rewardType');

        return $rewardType;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateRewardTypeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateRewardTypeData::fromValidated($validated);
    }
}

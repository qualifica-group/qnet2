<?php

namespace App\Http\Requests\RewardTypes;

use App\DataObjects\RewardTypes\CreateRewardTypeData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/reward-types (spec 0058).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', RewardType::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). `name` is unique (BR-1). `color` is
 * REQUIRED (BR-2, D-5) — no token allow-list at this layer (D-6).
 */
class StoreRewardTypeRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('reward_types', 'name')],
            'color' => ['required', 'string', 'max:32'],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateRewardTypeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateRewardTypeData::fromValidated($validated);
    }
}

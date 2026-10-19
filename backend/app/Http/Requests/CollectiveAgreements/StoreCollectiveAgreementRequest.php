<?php

namespace App\Http\Requests\CollectiveAgreements;

use App\DataObjects\CollectiveAgreements\CollectiveAgreementData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/collective-agreements (spec 0272).
 * Authorization stays in the controller (CollectiveAgreementPolicy).
 */
class StoreCollectiveAgreementRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int NAME_MAX = 191;

    public const int CODE_MAX = 50;

    public const int LEVEL_DESCRIPTION_MAX = 191;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('collective_agreements', 'name')],
            ...$this->sharedRules(),
            'levels.*.id' => ['prohibited'],
        ];
    }

    /**
     * Rules common to store and update (code and the level rows).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'code' => ['sometimes', 'nullable', 'string', 'max:'.self::CODE_MAX],
            'levels' => ['sometimes', 'nullable', 'array'],
            'levels.*' => ['array'],
            'levels.*.code' => ['required', 'string', 'max:'.self::CODE_MAX, 'distinct'],
            'levels.*.description' => ['nullable', 'string', 'max:'.self::LEVEL_DESCRIPTION_MAX],
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
        return 'collective-agreements';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): CollectiveAgreementData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CollectiveAgreementData::fromValidated($validated);
    }
}

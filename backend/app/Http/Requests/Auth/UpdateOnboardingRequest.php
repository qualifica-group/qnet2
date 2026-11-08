<?php

namespace App\Http\Requests\Auth;

use App\Enums\HttpStatusEnum;
use App\Enums\OnboardingStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Welcome tour registration (spec 0368). Refused with 403 from an impersonation
 * session (the admin must not mark the tour as seen for the impersonated user);
 * "skipped" is refused with 422 while the tour was never seen.
 */
class UpdateOnboardingRequest extends FormRequest
{
    private const string OUTCOME_COMPLETED = 'completed';

    private const string OUTCOME_SKIPPED = 'skipped';

    public function authorize(): bool
    {
        // Falsy, not `=== null`: a mocked token (Sanctum::actingAs) answers `false`
        // for unset attributes, and a real impersonation id is always a positive int.
        return ! $this->user()?->currentAccessToken()?->impersonated_by;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('auth.onboarding_impersonation'),
        ], HttpStatusEnum::FORBIDDEN->value));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'in:'.self::OUTCOME_COMPLETED.','.self::OUTCOME_SKIPPED],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('outcome') === self::OUTCOME_SKIPPED
                && $this->user()->onboardingStatus() === OnboardingStatusEnum::Required) {
                $validator->errors()->add('outcome', __('auth.onboarding_required_not_skippable'));
            }
        });
    }
}

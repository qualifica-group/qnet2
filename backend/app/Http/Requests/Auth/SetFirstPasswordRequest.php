<?php

namespace App\Http\Requests\Auth;

use App\Enums\HttpStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * First-access password choice (spec 0177 rev. 2). Accepted only while the user
 * is flagged `must_set_password` and never from an impersonation session (the
 * admin must not change the impersonated user's password).
 */
class SetFirstPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->must_set_password
            && $user->currentAccessToken()?->impersonated_by === null;
    }

    /**
     * The framework default 403 body lacks the project envelope, and no global
     * renderer covers a FormRequest denial: answer on-contract here.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('This action is unauthorized.'),
        ], HttpStatusEnum::FORBIDDEN->value));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $password = $this->input('password');

            if (is_string($password) && Hash::check($password, $this->user()->password)) {
                $validator->errors()->add('password', __('auth.first_password_same'));
            }
        });
    }
}

<?php

namespace App\Http\Requests\PaymentMethods;

use App\DataObjects\PaymentMethods\CreatePaymentMethodData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/payment-methods (spec 0068).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', PaymentMethod::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). `code` is the ONLY unique field (user
 * directive 2026-08-05: the name is a plain label, homonyms are legitimate)
 * and this is the ONLY place it is ever writable (D-3): permanently immutable
 * once persisted, enforced by UpdatePaymentMethodRequest's `prohibited` rule.
 * `payment_method_code` is the fiscal/legacy classification code, optional and
 * deliberately non-unique (several methods share e.g. "MP01").
 * `sort_order` is not accepted here (absent from rules() -> validated()
 * silently drops it) — server-managed, see
 * App\Services\PaymentMethods\PaymentMethodOrderManager.
 */
class StorePaymentMethodRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int CODE_MAX = 64;

    private const int PAYMENT_METHOD_CODE_MAX = 32;

    private const int DESCRIPTION_MAX = 500;

    private const int INSTRUCTIONS_MAX = 5000;

    private const int PAYMENT_DAYS_MAX = 3650;

    public function authorize(): bool
    {
        // Authorization handled in the controller via PaymentMethodPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('payment_methods', 'code')],
            'payment_method_code' => ['sometimes', 'nullable', 'string', 'max:'.self::PAYMENT_METHOD_CODE_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'payment_instructions' => ['sometimes', 'nullable', 'string', 'max:'.self::INSTRUCTIONS_MAX],
            'payment_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::PAYMENT_DAYS_MAX],
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
        return 'payment-methods';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreatePaymentMethodData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreatePaymentMethodData::fromValidated($validated);
    }
}

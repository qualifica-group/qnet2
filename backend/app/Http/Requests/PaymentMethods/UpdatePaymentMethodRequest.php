<?php

namespace App\Http\Requests\PaymentMethods;

use App\DataObjects\PaymentMethods\UpdatePaymentMethodData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/payment-methods/{paymentMethod}
 * (spec 0068). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $paymentMethod)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model. `name` carries no uniqueness rule (user directive
 * 2026-08-05: `code` is the only unique field); `payment_method_code` is the
 * optional, non-unique fiscal/legacy classification code.
 *
 * `code` (D-3): `prohibited`, UNCONDITIONALLY — the key must not even be
 * present in the payload, regardless of its value (identical or different
 * from the persisted one) and regardless of the actor's role. Field
 * permissions alone cannot express this: `AbstractResourceAuthorization::
 * fieldPermissions()` bypasses every ceiling for the privileged role, so the
 * immutability guard lives here instead, ahead of and independent from that
 * mechanism. `sort_order` is not accepted here (server-managed, see
 * App\Services\PaymentMethods\PaymentMethodOrderManager).
 */
class UpdatePaymentMethodRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

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
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX],
            'code' => ['prohibited'],
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
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $this->route('paymentMethod');

        return $paymentMethod;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdatePaymentMethodData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdatePaymentMethodData::fromValidated($validated);
    }
}

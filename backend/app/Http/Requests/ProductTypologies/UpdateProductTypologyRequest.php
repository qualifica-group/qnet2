<?php

namespace App\Http\Requests\ProductTypologies;

use App\DataObjects\ProductTypologies\UpdateProductTypologyData;
use App\Enums\SupplierCommissionDirection;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\ProductTypology;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH
 * /api/product-typologies/{productTypology} (spec 0099). Every field is
 * `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $productTypology)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit on this specific model.
 *
 * `code` (D-2): `prohibited`, UNCONDITIONALLY — the key must not even be
 * present in the payload, regardless of its value and regardless of the
 * actor's role. Field permissions alone cannot express this: the privileged
 * role bypasses every ceiling, so the immutability guard lives here instead,
 * ahead of and independent from that mechanism (mirrors
 * UpdateUnitOfMeasureRequest).
 */
class UpdateProductTypologyRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProductTypologyPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $productTypology = $this->route('productTypology');
        $ignoreId = $productTypology instanceof ProductTypology ? $productTypology->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('product_typologies', 'name')->ignore($ignoreId)],
            'code' => ['prohibited'],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'supplier_commission_enabled' => ['sometimes', 'boolean'],
            'supplier_commission_direction' => $this->directionRules(),
        ];
    }

    /**
     * The direction is mandatory when the switch is on (spec 0202, D-7); with
     * the switch off a submitted value is accepted but discarded (the Service
     * forces null).
     *
     * @return array<int, mixed>
     */
    private function directionRules(): array
    {
        return [
            'nullable',
            Rule::requiredIf(fn (): bool => $this->boolean('supplier_commission_enabled')),
            Rule::enum(SupplierCommissionDirection::class),
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
        return 'product-typologies';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var ProductTypology $productTypology */
        $productTypology = $this->route('productTypology');

        return $productTypology;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateProductTypologyData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateProductTypologyData::fromValidated($validated);
    }
}

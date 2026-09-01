<?php

namespace App\Http\Requests\UnitsOfMeasure;

use App\DataObjects\UnitsOfMeasure\UpdateUnitOfMeasureData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/units-of-measure/{unitOfMeasure}
 * (spec 0088). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $unitOfMeasure)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model.
 *
 * `code` (D-1): `prohibited`, UNCONDITIONALLY — the key must not even be
 * present in the payload, regardless of its value and regardless of the
 * actor's role. Field permissions alone cannot express this: the privileged
 * role bypasses every ceiling, so the immutability guard lives here instead,
 * ahead of and independent from that mechanism (mirrors
 * UpdatePaymentMethodRequest).
 */
class UpdateUnitOfMeasureRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int SYMBOL_MAX = 16;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        // Authorization handled in the controller via UnitOfMeasurePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $unitOfMeasure = $this->route('unitOfMeasure');
        $ignoreId = $unitOfMeasure instanceof UnitOfMeasure ? $unitOfMeasure->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('units_of_measure', 'name')->ignore($ignoreId)],
            'symbol' => ['sometimes', 'required', 'string', 'max:'.self::SYMBOL_MAX, Rule::unique('units_of_measure', 'symbol')->ignore($ignoreId)],
            'code' => ['prohibited'],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
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
        return 'units-of-measure';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var UnitOfMeasure $unitOfMeasure */
        $unitOfMeasure = $this->route('unitOfMeasure');

        return $unitOfMeasure;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateUnitOfMeasureData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateUnitOfMeasureData::fromValidated($validated);
    }
}

<?php

namespace App\Http\Requests\UnitsOfMeasure;

use App\DataObjects\UnitsOfMeasure\CreateUnitOfMeasureData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/units-of-measure (spec 0088).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', UnitOfMeasure::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null). `code` is the
 * ONLY place it is ever writable (D-1): permanently immutable once
 * persisted, enforced by UpdateUnitOfMeasureRequest's own `prohibited` rule.
 */
class StoreUnitOfMeasureRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int CODE_MAX = 64;

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
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('units_of_measure', 'name')],
            'symbol' => ['required', 'string', 'max:'.self::SYMBOL_MAX, Rule::unique('units_of_measure', 'symbol')],
            'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('units_of_measure', 'code')],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateUnitOfMeasureData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateUnitOfMeasureData::fromValidated($validated);
    }
}

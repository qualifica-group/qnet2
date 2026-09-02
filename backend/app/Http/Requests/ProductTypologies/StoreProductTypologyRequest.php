<?php

namespace App\Http\Requests\ProductTypologies;

use App\DataObjects\ProductTypologies\CreateProductTypologyData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/product-typologies (spec 0099).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', ProductTypology::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null). `code` is the
 * ONLY place it is ever writable (D-2): permanently immutable once
 * persisted, enforced by UpdateProductTypologyRequest's own `prohibited`
 * rule.
 */
class StoreProductTypologyRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int CODE_MAX = 64;

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
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('product_typologies', 'name')],
            'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('product_typologies', 'code')],
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
        return 'product-typologies';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateProductTypologyData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateProductTypologyData::fromValidated($validated);
    }
}

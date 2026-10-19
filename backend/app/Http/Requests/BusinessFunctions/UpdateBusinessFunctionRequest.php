<?php

namespace App\Http\Requests\BusinessFunctions;

use App\DataObjects\BusinessFunctions\UpdateBusinessFunctionData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\BusinessFunction;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/business-functions/{businessFunction}
 * (spec 0010). Every field is `sometimes` to support partial PATCH updates:
 * `users` is a full-replace sync when submitted, `manager_id:null` removes the
 * manager, `type` re-maps the two boolean columns when submitted.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $businessFunction)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on this
 * specific model.
 */
class UpdateBusinessFunctionRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via BusinessFunctionPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'type' => ['sometimes', 'nullable', Rule::in(['business_unit', 'business_service'])],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:business_functions,id'],
            'is_active' => ['sometimes', 'boolean'],
            'users' => ['sometimes', 'nullable', 'array'],
            'users.*' => ['integer', 'exists:users,id', 'distinct'],
            'operational_sites' => ['sometimes', 'nullable', 'array'],
            'operational_sites.*' => ['integer', 'exists:operational_sites,id', 'distinct'],
            'company_ids' => ['sometimes', 'nullable', 'array'],
            'company_ids.*' => ['integer', 'distinct', 'exists:companies,id'],
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
        return 'business-functions';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var BusinessFunction $businessFunction */
        $businessFunction = $this->route('businessFunction');

        return $businessFunction;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateBusinessFunctionData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateBusinessFunctionData::fromValidated($validated);
    }
}

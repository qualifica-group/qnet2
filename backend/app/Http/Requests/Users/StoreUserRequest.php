<?php

namespace App\Http\Requests\Users;

use App\DataObjects\Users\CreateUserData;
use App\Enums\LocaleEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ResolvesAssignableRoles;
use App\Http\Requests\Concerns\ValidatesEmployment;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the payload for POST /api/users.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', User::class)). Rules mirror the existing profile /
 * password conventions: email unique, locale within the supported set, password
 * with the framework defaults, and roles validated against the real Spatie roles.
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted field
 * the actor cannot edit (create-context, model = null).
 */
class StoreUserRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ResolvesAssignableRoles;
    use ValidatesEmployment;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the UserPolicy.
        return true;
    }

    /**
     * `personal_data` is mandatory on create: it is the only source of the derived
     * `users.name` (ADR 0012).
     */
    protected function profileRequired(): bool
    {
        return true;
    }

    /**
     * A present address must be geo-located on create (product decision): both
     * `line1` and `city_id` are required. Update keeps `city_id` optional
     * (ValidatesUserProfile default) so legacy addresses without a city stay
     * editable.
     */
    protected function addressCityRequired(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            // `name` is no longer client-supplied: it is derived from the required
            // nested personal_data card (ADR 0012) by the UserService.
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'locale' => ['required', Rule::in(LocaleEnum::values())],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            // Role IDS the current actor may assign (for-select, ADR 0011): a non
            // super-admin cannot assign `super-admin` (privilege escalation), even
            // by id. Resolved back to names in toData() for the service/guard.
            'roles.*' => $this->assignableRoleIdRule(),
        ], $this->profileRules(), $this->employmentRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile
     * (ADR 0012), the cross-row pair rules of the nested
     * `employment.product_lines` competence (spec 0111) and the field-level
     * authorization gate (spec 0004).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->validateEmploymentProductLines($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'users';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateUserData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateUserData::fromValidated($this->withResolvedRoleNames($validated));
    }
}

<?php

namespace App\Http\Requests\Users;

use App\DataObjects\Users\UpdateUserData;
use App\Enums\LocaleEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ResolvesAssignableRoles;
use App\Http\Requests\Concerns\ValidatesEmployment;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the payload for PUT/PATCH /api/users/{user}.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $user)). Rules mirror StoreUserRequest but every
 * field is optional (`sometimes`) to support partial PATCH updates. The email
 * unique rule ignores the user being edited. Password change is optional here,
 * replicating the framework password defaults; the dedicated self-service flow
 * (PUT /api/auth/me/password) remains the path for a user changing their own
 * password with the current-password check.
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted field
 * the actor cannot edit on this specific $user (e.g. `roles` on a super-admin
 * target for a non-super-admin actor).
 */
class UpdateUserRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return array_merge([
            // `name` is no longer client-supplied: when a personal_data card is
            // submitted the name is re-derived from it (ADR 0012); otherwise it is
            // left untouched.
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'locale' => ['sometimes', 'required', Rule::in(LocaleEnum::values())],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'required', 'confirmed', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            // Role IDS the current actor may assign (for-select, ADR 0011): a non
            // super-admin cannot assign `super-admin` (privilege escalation), even
            // by id. Resolved back to names in toData() for the service/guard.
            'roles.*' => $this->assignableRoleIdRule(),
        ], $this->profileRules(), $this->employmentRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile
     * (ADR 0012) and the field-level authorization gate (spec 0004).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'users';
    }

    /**
     * The field-permission diff (spec 0008) reads
     * `employment.primary_operational_site_id` and
     * `employment.remote_operational_site_ids`, which since spec 0103 are
     * accessors over the site-membership pivot rather than plain columns.
     * Loading the relation here keeps that comparison to the queries this
     * request already needs, instead of one lazy load per checked field.
     */
    protected function authorizationModel(): ?Model
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user->loadMissing('employment.operationalSites');
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateUserData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateUserData::fromValidated($this->withResolvedRoleNames($validated));
    }
}

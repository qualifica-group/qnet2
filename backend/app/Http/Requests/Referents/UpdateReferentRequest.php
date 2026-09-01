<?php

namespace App\Http\Requests\Referents;

use App\DataObjects\Referents\UpdateReferentData;
use App\Enums\ReferentContactScopeEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesPhoneUniqueness;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use App\Http\Requests\Referents\Concerns\ValidatesReferentUserLink;
use App\Models\Referent;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/referents/{referent} (spec 0016).
 * Every field is `sometimes` to support partial PATCH updates:
 * `referent_type_id:null` removes the type; `personal_data` is optional
 * (absent leaves the card untouched, mirrors UpdateUserRequest).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $referent)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $referent.
 */
class UpdateReferentRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesPhoneUniqueness;
    use ValidatesReferentUserLink;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the ReferentPolicy.
        return true;
    }

    /**
     * Codice fiscale, partita IVA and phone numbers must be free across users,
     * anagrafiche and referenti (user directive 2026-08-06), the referent under
     * edit excluded — keeping its own values must stay a no-op, not a
     * self-collision.
     *
     * @return class-string<Referent>
     */
    protected function identityUniquenessOwner(): ?string
    {
        return Referent::class;
    }

    protected function identityUniquenessOwnerId(): ?int
    {
        return $this->routeReferent()?->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'referent_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referent_types', 'id')],
            'contact_scope' => ['sometimes', 'required', Rule::enum(ReferentContactScopeEnum::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
        ], $this->profileRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile and
     * the field-level authorization gate (spec 0004).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->validatePhoneUniqueness($validator);
            $this->validateUserLinkUniqueness($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'referents';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->routeReferent();
    }

    /** The bound referent under edit, shared by the authz and uniqueness hooks. */
    private function routeReferent(): ?Referent
    {
        /** @var Referent|null $referent */
        $referent = $this->route('referent');

        return $referent;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects). The
     * nested profile is read separately via toProfile() (ValidatesUserProfile).
     */
    public function toData(): UpdateReferentData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateReferentData::fromValidated($validated);
    }
}

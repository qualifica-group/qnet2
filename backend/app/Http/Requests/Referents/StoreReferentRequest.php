<?php

namespace App\Http\Requests\Referents;

use App\DataObjects\Referents\CreateReferentData;
use App\Enums\ContactTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesReferentContactUniqueness;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/referents (spec 0016).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Referent::class)). Reuses
 * ValidatesUserProfile verbatim for the nested `personal_data` object
 * (required on create — it is the only source of the derived `referents.name`,
 * mirroring StoreUserRequest/ADR 0012). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 *
 * On top of those, two domain rules of its own: the nested card must carry at
 * least one phone number (see validatePhoneContact), and that number must not
 * already belong to another referent (ValidatesReferentContactUniqueness).
 */
class StoreReferentRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesReferentContactUniqueness;
    use ValidatesUserProfile;

    /**
     * Contact types that satisfy the create-time phone requirement (user
     * directive 2026-07-31). Mobile counts: it is a telephone number, and a
     * referent reachable only on a mobile is no less reachable.
     *
     * @var list<string>
     */
    private const array PHONE_CONTACT_TYPES = [
        ContactTypeEnum::Phone->value,
        ContactTypeEnum::Mobile->value,
    ];

    public function authorize(): bool
    {
        // Authorization handled in the controller via the ReferentPolicy.
        return true;
    }

    /**
     * `personal_data` is mandatory on create: it is the only source of the
     * derived `referents.name` (mirrors StoreUserRequest, ADR 0012).
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
     * Codice fiscale and partita IVA are unique among REFERENTI (user directive
     * 2026-08-03) — not globally: the same person may also exist as an
     * anagraphic record, which is a different role, not a duplicate.
     *
     * @return class-string<Referent>
     */
    protected function identityUniquenessOwner(): ?string
    {
        return Referent::class;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'referent_type_id' => ['nullable', 'integer', Rule::exists('referent_types', 'id')],
            'contact_scope' => ['required', Rule::enum(ReferentContactScopeEnum::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], $this->profileRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile, the
     * create-time phone requirement and the field-level authorization gate
     * (spec 0004).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->validatePhoneContact($validator);
            $this->validateContactUniqueness($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    /**
     * A referent must carry at least one phone number at creation (user
     * directive 2026-07-31): the module exists to make people reachable, and
     * one created without a number is dead weight in the commercial flow.
     *
     * Create-only, by design: this is a gate on how a referent enters the
     * system, not an invariant the update path re-asserts.
     */
    private function validatePhoneContact(Validator $validator): void
    {
        /** @var User $actor */
        $actor = $this->user();

        if (! $actor->can('referents.create')) {
            // Same reason EnforcesFieldPermissions steps aside here: for an
            // actor who may not create at all, the relevant failure is the
            // Policy's 403, and a 422 raised first would mask it.
            return;
        }

        if ($validator->errors()->isNotEmpty()) {
            // The payload is already malformed; this would only add noise on
            // top of it (same guard validateProfile applies).
            return;
        }

        /** @var array<int, mixed> $contacts */
        $contacts = (array) $this->input('personal_data.contacts', []);

        foreach ($contacts as $row) {
            if (is_array($row) && in_array($row['type'] ?? null, self::PHONE_CONTACT_TYPES, true)) {
                return;
            }
        }

        $validator->errors()->add('personal_data.contacts', 'At least one phone number is required.');
    }

    protected function authorizationResource(): string
    {
        return 'referents';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects). The
     * nested profile is read separately via toProfile() (ValidatesUserProfile).
     */
    public function toData(): CreateReferentData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateReferentData::fromValidated($validated);
    }
}

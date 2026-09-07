<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An identity must carry at least one phone number AT CREATION: the modules
 * that hold people exist to make them reachable, and a record created without
 * a number is dead weight in the commercial flow. Applied to referenti (user
 * directive 2026-07-31) and to anagrafiche (user directive 2026-09-07).
 *
 * Create-only, by design: this is a gate on how an identity enters the system,
 * not an invariant the update path re-asserts. It also lives in the create
 * FormRequest and NOT in the service, so the paths that legitimately create a
 * record without contacts — import, lead conversion, seeders — keep working.
 *
 * "A phone number" means the same channels the uniqueness gate pools: a person
 * reachable only on a mobile is no less reachable. That list is
 * `PHONE_CONTACT_TYPES`, owned by `ValidatesPhoneUniqueness` — which the host
 * request MUST also use, since a trait constant is only reachable through the
 * class that composes it.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesRequiredPhoneContact
{
    protected function validateRequiredPhoneContact(Validator $validator): void
    {
        /** @var User $actor */
        $actor = $this->user();

        if (! $actor->can($this->authorizationResource().'.create')) {
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

    /** Domain key of the resource, e.g. `referents` — see EnforcesFieldPermissions. */
    abstract protected function authorizationResource(): string;
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Support\ContactValueNormalizer;
use App\Support\IdentityUniquenessScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A phone number is unique across the shared identity namespace — users,
 * anagrafiche and referenti (user directive 2026-08-06, see
 * `IdentityUniquenessScope`) — and `phone` and `mobile` are ONE namespace, not
 * two: the same number cannot sit on one card's landline and on another's
 * mobile. This supersedes the per-channel, referent-only scope of 2026-08-03.
 *
 * Distinct from `IdentityDuplicateFinder` (spec 0037), which answers the live,
 * NON-blocking duplicate panel on the anagrafica/referente create forms: this
 * one is the blocking gate on write. Both compare through
 * `ContactValueNormalizer` and both search the same namespace, so the panel can
 * no longer stay silent on a number the gate then refuses.
 *
 * Reads the owner of the card under edit from `ValidatesUserProfile`
 * (`identityUniquenessOwner`/`identityUniquenessOwnerId`), which every host
 * request composes: the exclusion is identical for the fiscal columns and for
 * the contact rows, so it is declared once per request, not twice.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesPhoneUniqueness
{
    /**
     * The channels the constraint covers, as one pooled namespace. Fax, email
     * and website are out: an email has its own uniqueness elsewhere in the
     * stack, and a shared fax line is normal.
     *
     * @var array<int, string>
     */
    private const array PHONE_CONTACT_TYPES = [
        'phone',
        'mobile',
    ];

    /**
     * The payload key holding the contact rows to check. Surfaces nesting them
     * elsewhere override it — the request-management create form carries the
     * client's contacts under `client_contacts`, not under a `personal_data`
     * card.
     */
    protected function phoneUniquenessContactsKey(): string
    {
        return 'personal_data.contacts';
    }

    /**
     * After-hook: every submitted phone/mobile row is checked against the cards
     * in the namespace AND against the rest of the same payload (submitting the
     * same number twice on one card is the same violation, caught before the
     * write).
     */
    protected function validatePhoneUniqueness(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            // The payload is already malformed; this would only add noise on
            // top of it (same guard validateProfile applies).
            return;
        }

        $key = $this->phoneUniquenessContactsKey();

        /** @var array<int, mixed> $rows */
        $rows = (array) $this->input($key, []);
        $seen = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! in_array($row['type'] ?? null, self::PHONE_CONTACT_TYPES, true)) {
                continue;
            }

            $normalized = $this->normalizePhone((string) ($row['value'] ?? ''));

            if ($normalized === '') {
                continue;
            }

            if (isset($seen[$normalized])) {
                $validator->errors()->add(
                    "{$key}.{$index}.value",
                    __('The phone number is repeated on this card.'),
                );

                continue;
            }

            $seen[$normalized] = true;

            if ($this->phoneValueTaken($normalized)) {
                $validator->errors()->add(
                    "{$key}.{$index}.value",
                    __('The phone number is already assigned to another record.'),
                );
            }
        }
    }

    /**
     * Whether another card in the namespace already carries this number, on
     * either channel.
     *
     * The candidate set is fetched and compared in PHP rather than in SQL:
     * phone formatting varies too much for a portable transform, and
     * legacy/migrated rows were never canonicalized by `InputFormat`. Mirrors
     * `IdentityDuplicateFinder::matchPhoneLike` verbatim.
     */
    private function phoneValueTaken(string $normalized): bool
    {
        $cards = IdentityUniquenessScope::cards(
            $this->identityUniquenessOwner(),
            $this->identityUniquenessOwnerId(),
        )->select('id');

        return Contact::query()
            ->where('contactable_type', (new PersonalData)->getMorphClass())
            ->whereIn('type', self::PHONE_CONTACT_TYPES)
            ->whereIn('contactable_id', $cards)
            ->pluck('value')
            ->contains(fn (mixed $value): bool => $this->normalizePhone((string) $value) === $normalized);
    }

    /**
     * Phone and mobile are pooled, and `ContactValueNormalizer` treats every
     * non-email channel identically (digits and `+` only), so one channel
     * stands for both here.
     */
    private function normalizePhone(string $value): string
    {
        return ContactValueNormalizer::contact(ContactTypeEnum::Phone, $value);
    }
}

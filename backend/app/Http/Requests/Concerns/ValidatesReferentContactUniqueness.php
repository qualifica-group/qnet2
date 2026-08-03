<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Support\ContactValueNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A referent's phone and mobile number are unique among referents (user
 * directive 2026-08-03), PER CHANNEL: the same number may sit on a referent's
 * `phone` and on another's `mobile` — the two are distinct channels and the
 * decision was to keep them separate namespaces.
 *
 * Distinct from `ReferentDuplicateFinder` (spec 0037), which answers the live,
 * NON-blocking duplicate panel on the create form: this one is the blocking
 * gate on write. Both compare through `ContactValueNormalizer`, so what the
 * panel warns about is exactly what the save rejects — never fork the two.
 *
 * Scoped to referent-owned cards only: `contacts` is shared with users,
 * registries and company sites through the `contactable` morph, and a client's
 * switchboard number legitimately equals its own contact person's.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesReferentContactUniqueness
{
    /**
     * The channels the constraint covers. Fax/email/website are out: an email
     * already has its own uniqueness elsewhere in the stack, and a shared fax
     * line is normal.
     *
     * @var array<int, ContactTypeEnum>
     */
    private const array UNIQUE_CONTACT_TYPES = [ContactTypeEnum::Phone, ContactTypeEnum::Mobile];

    /**
     * The referent being updated, excluded from the lookup so its own numbers
     * do not collide with themselves. Null on create.
     */
    protected function contactUniquenessIgnoreId(): ?int
    {
        return null;
    }

    /**
     * After-hook: every submitted phone/mobile row is checked against the other
     * referents AND against the rest of the same payload (submitting the same
     * number twice on one card is the same violation, caught before the write).
     */
    protected function validateContactUniqueness(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            // The payload is already malformed; this would only add noise on
            // top of it (same guard validateProfile applies).
            return;
        }

        /** @var array<int, mixed> $rows */
        $rows = (array) $this->input('personal_data.contacts', []);
        $seen = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = ContactTypeEnum::tryFrom((string) ($row['type'] ?? ''));

            if ($type === null || ! in_array($type, self::UNIQUE_CONTACT_TYPES, true)) {
                continue;
            }

            $normalized = ContactValueNormalizer::contact($type, (string) ($row['value'] ?? ''));

            if ($normalized === '') {
                continue;
            }

            $key = $type->value.'|'.$normalized;

            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "personal_data.contacts.{$index}.value",
                    __('The phone number is repeated on this card.'),
                );

                continue;
            }

            $seen[$key] = true;

            if ($this->contactValueTaken($type, $normalized)) {
                $validator->errors()->add(
                    "personal_data.contacts.{$index}.value",
                    __('The phone number is already assigned to another referent.'),
                );
            }
        }
    }

    /**
     * Whether another referent already carries this number on this channel.
     *
     * The candidate set is fetched per channel and compared in PHP rather than
     * in SQL: phone formatting varies too much for a portable transform, and
     * legacy/migrated rows were never canonicalized by `InputFormat`. Mirrors
     * `ReferentDuplicateFinder::matchPhoneLike` verbatim.
     */
    private function contactValueTaken(ContactTypeEnum $type, string $normalized): bool
    {
        $ignoreId = $this->contactUniquenessIgnoreId();

        $referentCards = PersonalData::query()
            ->select('id')
            ->where('personable_type', (new Referent)->getMorphClass())
            ->when($ignoreId !== null, fn ($query) => $query->where('personable_id', '!=', $ignoreId));

        return Contact::query()
            ->where('contactable_type', (new PersonalData)->getMorphClass())
            ->where('type', $type->value)
            ->whereIn('contactable_id', $referentCards)
            ->pluck('value')
            ->contains(fn (mixed $value): bool => ContactValueNormalizer::contact($type, (string) $value) === $normalized);
    }
}

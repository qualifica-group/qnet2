<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Identity\IdentityDuplicateCriteria;
use App\DataObjects\Identity\IdentityDuplicateMatch;
use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Support\ContactValueNormalizer;
use App\Support\IdentityUniquenessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Finds the EXISTING holders of a codice fiscale, a partita IVA or an
 * email/phone/mobile contact, backing the live, non-blocking duplicate panel
 * of the anagrafica and referente create forms (spec 0037, extended by the
 * user directive 2026-09-09: also CF and P.IVA, and across the whole
 * namespace).
 *
 * It searches `IdentityUniquenessScope` — users, anagrafiche and referenti —
 * NOT one module at a time: the panel and the blocking write gate
 * (`UniquePersonalDataIdentifier`/`ValidatesPhoneUniqueness`) must answer the
 * same question, otherwise the operator is told "no duplicate" and then has
 * the save refused on the very same value.
 *
 * Shares its normalization semantics with `LeadDuplicateMatcher` via
 * `ContactValueNormalizer`, but queries per channel with bound `whereRaw` for
 * the deterministic transforms (email/fiscal case) instead of hydrating a
 * whole contact type, since this runs on every debounced keystroke rather
 * than once per import row.
 */
final class IdentityDuplicateFinder
{
    private const int MAX_MATCHES = 5;

    /** Canonical, deterministic order for `IdentityDuplicateMatch::$matchedOn`. */
    private const array MATCH_ORDER = ['email', 'phone', 'mobile', 'tax_code', 'vat_number'];

    /**
     * The fiscal columns the check covers. The value reaches `whereRaw` as a
     * bound parameter, but the COLUMN is interpolated into the SQL, so it is
     * allow-listed here and can never originate from request input
     * (backend.md §8) — same guard `UniquePersonalDataIdentifier` applies.
     *
     * @var array<int, string>
     */
    private const array FISCAL_COLUMNS = ['tax_code', 'vat_number'];

    /**
     * @return array<int, IdentityDuplicateMatch>
     */
    public function find(IdentityDuplicateCriteria $criteria): array
    {
        // Step 1: normalize every criterion into its comparable target(s).
        $contactTargets = $this->contactTargets($criteria->contacts);
        $fiscalTargets = $this->fiscalTargets($criteria);

        // Step 2: personal_data.id => matched channel(s), via targeted
        // per-type contact queries (never a scan across unrelated types).
        $channelsByCardId = $this->collectContactMatches($contactTargets);

        // Step 3: merge in the fiscal columns, which live on the card itself.
        foreach ($fiscalTargets as $column => $target) {
            foreach ($this->matchFiscalColumn($column, $target) as $cardId) {
                $channelsByCardId[$cardId] = $this->mergeChannel($channelsByCardId[$cardId] ?? [], $column);
            }
        }

        // Step 4: keep only the cards inside the namespace, cap to 5, and
        // hydrate each owner's display name.
        return $this->buildMatches($channelsByCardId);
    }

    /**
     * @param  array<int, array{type: string, value: string}>  $contacts
     * @return array<string, array<int, string>> ContactTypeEnum value => normalized targets
     */
    private function contactTargets(array $contacts): array
    {
        $targets = [];

        foreach ($contacts as $contact) {
            $type = ContactTypeEnum::tryFrom((string) ($contact['type'] ?? ''));
            $value = trim((string) ($contact['value'] ?? ''));

            if ($type === null || $value === '' || ! in_array($type, [ContactTypeEnum::Email, ContactTypeEnum::Phone, ContactTypeEnum::Mobile], true)) {
                continue;
            }

            $targets[$type->value][] = ContactValueNormalizer::contact($type, $value);
        }

        return array_map(
            static fn (array $values): array => array_values(array_unique($values)),
            $targets,
        );
    }

    /**
     * The submitted fiscal identifiers, normalized, keyed by their column —
     * absent when blank. `ContactValueNormalizer::taxCode` (upper + trim) is
     * what `UniquePersonalDataIdentifier` applies to BOTH columns, so the
     * panel and the write gate can never disagree on "the same P.IVA".
     *
     * @return array<string, string>
     */
    private function fiscalTargets(IdentityDuplicateCriteria $criteria): array
    {
        $submitted = [
            'tax_code' => $criteria->taxCode,
            'vat_number' => $criteria->vatNumber,
        ];

        $targets = [];

        foreach (self::FISCAL_COLUMNS as $column) {
            $value = trim((string) ($submitted[$column] ?? ''));

            if ($value !== '') {
                $targets[$column] = ContactValueNormalizer::taxCode($value);
            }
        }

        return $targets;
    }

    /**
     * @param  array<string, array<int, string>>  $contactTargets
     * @return array<int, array<int, string>> personal_data.id => channels
     */
    private function collectContactMatches(array $contactTargets): array
    {
        $morph = (new PersonalData)->getMorphClass();
        $channelsByCardId = [];

        // Email: a single deterministic transform (LOWER), matched directly
        // in SQL with bound placeholders.
        if (($contactTargets[ContactTypeEnum::Email->value] ?? []) !== []) {
            foreach ($this->matchEmail($contactTargets[ContactTypeEnum::Email->value], $morph) as $cardId) {
                $channelsByCardId[$cardId] = $this->mergeChannel($channelsByCardId[$cardId] ?? [], ContactTypeEnum::Email->value);
            }
        }

        // Phone/mobile: formatting varies too much for a portable SQL
        // transform, so fetch the bounded per-type candidate set and
        // normalize in PHP (mirrors LeadDuplicateMatcher).
        foreach ([ContactTypeEnum::Phone, ContactTypeEnum::Mobile] as $type) {
            if (($contactTargets[$type->value] ?? []) === []) {
                continue;
            }

            foreach ($this->matchPhoneLike($type, $contactTargets[$type->value], $morph) as $cardId) {
                $channelsByCardId[$cardId] = $this->mergeChannel($channelsByCardId[$cardId] ?? [], $type->value);
            }
        }

        return $channelsByCardId;
    }

    /**
     * @param  array<int, string>  $targets
     * @return array<int, int> distinct personal_data.id (Contact::contactable_id)
     */
    private function matchEmail(array $targets, string $morph): array
    {
        $placeholders = implode(',', array_fill(0, count($targets), '?'));

        return Contact::query()
            ->where('contactable_type', $morph)
            ->where('type', ContactTypeEnum::Email->value)
            ->whereRaw("LOWER(value) IN ({$placeholders})", $targets)
            ->pluck('contactable_id')
            ->unique()
            ->all();
    }

    /**
     * @param  array<int, string>  $targets
     * @return array<int, int> distinct personal_data.id (Contact::contactable_id)
     */
    private function matchPhoneLike(ContactTypeEnum $type, array $targets, string $morph): array
    {
        return Contact::query()
            ->where('contactable_type', $morph)
            ->where('type', $type->value)
            ->get(['value', 'contactable_id'])
            ->filter(fn (Contact $contact): bool => in_array(
                ContactValueNormalizer::contact($type, (string) $contact->value),
                $targets,
                true,
            ))
            ->pluck('contactable_id')
            ->unique()
            ->all();
    }

    /**
     * @param  string  $column  one of FISCAL_COLUMNS
     * @return array<int, int> personal_data.id
     */
    private function matchFiscalColumn(string $column, string $target): array
    {
        return IdentityUniquenessScope::cards()
            ->whereNotNull($column)
            ->whereRaw("UPPER(TRIM({$column})) = ?", [$target])
            ->pluck('id')
            ->unique()
            ->all();
    }

    /**
     * @param  array<int, string>  $channels
     * @return array<int, string>
     */
    private function mergeChannel(array $channels, string $channel): array
    {
        return in_array($channel, $channels, true) ? $channels : [...$channels, $channel];
    }

    /**
     * @param  array<int, array<int, string>>  $channelsByCardId
     * @return array<int, IdentityDuplicateMatch>
     */
    private function buildMatches(array $channelsByCardId): array
    {
        if ($channelsByCardId === []) {
            return [];
        }

        // The scope is what excludes the cards owned by anything outside the
        // namespace (a company site, for instance), so a contact matched on a
        // foreign card simply drops out here.
        $cards = IdentityUniquenessScope::cards()
            ->whereIn('id', array_keys($channelsByCardId))
            ->orderByDesc('id')
            ->limit(self::MAX_MATCHES)
            ->get(['id', 'personable_type', 'personable_id']);

        $names = $this->ownerNames($cards);

        return $cards
            ->map(fn (PersonalData $card): IdentityDuplicateMatch => new IdentityDuplicateMatch(
                ownerType: (string) $card->personable_type,
                ownerId: (int) $card->personable_id,
                name: (string) ($names[(string) $card->personable_type][(int) $card->personable_id] ?? ''),
                matchedOn: array_values(array_intersect(self::MATCH_ORDER, $channelsByCardId[$card->id])),
            ))
            ->values()
            ->all();
    }

    /**
     * Display names of the matched owners, one query per morph alias present.
     *
     * @param  Collection<int, PersonalData>  $cards
     * @return array<string, array<int, string>> morph alias => [owner id => name]
     */
    private function ownerNames(Collection $cards): array
    {
        $names = [];

        foreach ($cards->groupBy('personable_type') as $alias => $group) {
            /** @var class-string<Model>|null $ownerClass */
            $ownerClass = Relation::getMorphedModel((string) $alias);

            if ($ownerClass === null) {
                continue;
            }

            $names[(string) $alias] = $ownerClass::query()
                ->whereIn('id', $group->pluck('personable_id')->all())
                ->pluck('name', 'id')
                ->all();
        }

        return $names;
    }
}

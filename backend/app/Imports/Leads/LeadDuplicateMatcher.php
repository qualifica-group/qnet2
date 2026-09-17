<?php

namespace App\Imports\Leads;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Support\ContactValueNormalizer;

/**
 * Resolves the EXISTING Registry (Anagrafica) a staged row collides with, by
 * email/phone (spec 0033 decision) or, additionally, by the card's
 * fiscal identifiers — `tax_code` (spec 0036) and `vat_number` (user directive
 * 2026-09-09). Spec 0041 D-1: the contact matched is a Registry,
 * not a Referent. Values are compared NORMALIZED (case/whitespace for email/
 * tax_code/vat_number, digits-only for phone).
 *
 * Spec 0136 (D-3/D-4): the candidate set is fetched with an INDEXED lookup —
 * `contacts.normalized_value` (`type`, `normalized_value`) for contacts,
 * plain equality on the fiscal column for cards — never a full scan of every
 * contact/card in the database normalized in PHP row by row. Only the fiscal
 * comparison still runs in PHP (`ContactValueNormalizer::taxCode` against the
 * equality-matched candidates), because MySQL's `utf8mb4_unicode_ci`
 * collation already folds case/trailing whitespace, so the indexed
 * `where($column, $target)` over-matches by at most a few rows, never by the
 * whole table. Backs `LeadsImportDefinition::resolveDuplicate()`/
 * `resolveDuplicateMatch()`.
 */
final class LeadDuplicateMatcher
{
    /** Canonical, deterministic order for `LeadDuplicateMatch::$matchedOn`. */
    private const array MATCH_ORDER = ['email', 'phone', 'tax_code', 'vat_number'];

    /**
     * The fiscal identifiers a row is matched on, in lookup order (user
     * directive 2026-09-09: a partita IVA identifies a client just as a codice
     * fiscale does). Both are card COLUMNS, allow-listed here so a mapped field
     * id can never decide which column is read.
     *
     * @var array<int, string>
     */
    private const array FISCAL_COLUMNS = ['tax_code', 'vat_number'];

    /**
     * The row's dominant Registry match — id, display name, and every
     * channel that matches it, cumulative — or null when nothing matches.
     *
     * @param  array<string, mixed>  $mapped  field id => resolved value (after recognizers)
     */
    public function match(array $mapped): ?LeadDuplicateMatch
    {
        // Step 1: an email/phone Contact match takes priority
        // (unchanged pre-0036 lookup); the fiscal columns are tried only when
        // no contact channel hits, keeping the existing semantics intact.
        $registryId = $this->matchByContact($mapped) ?? $this->matchByFiscalColumns($mapped);

        if ($registryId === null) {
            return null;
        }

        // Step 2: report every channel that ALSO matches the winning
        // registry (cumulative), not just whichever one found it first.
        return $this->buildMatch($registryId, $mapped);
    }

    /**
     * The id of the Lead already tying the given Registry to the row's
     * campaign, or null when none exists (either no lead at all, or only on
     * a DIFFERENT campaign) — spec 0036 AC-002. Spec 0108: "the row's
     * campaign" is the row's own resolved one when the run reads campaigns
     * from a file column, the run's global one otherwise (LeadRowCampaign).
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig
     */
    public function existingLeadId(int $registryId, array $mapped, array $globalConfig): ?int
    {
        $campaignId = LeadRowCampaign::resolve($mapped, $globalConfig);

        if ($campaignId === null) {
            return null;
        }

        /** @var int|null $leadId */
        $leadId = Lead::query()
            ->where('registry_id', $registryId)
            ->where('campaign_id', $campaignId)
            ->value('id');

        return $leadId;
    }

    /**
     * One indexed query on `(type, normalized_value)`, conditions grouped
     * per type present in the row and OR-ed together, ordered by `id` asc —
     * the SAME evaluation order as the former full-scan (a single unordered
     * fetch across every mappable type falls back to insertion/id order),
     * so "the first candidate whose card is a Registry wins" is unchanged.
     *
     * @param  array<string, mixed>  $mapped
     */
    private function matchByContact(array $mapped): ?int
    {
        $targets = $this->normalizedTargets($mapped);

        if ($targets === []) {
            return null;
        }

        $contacts = Contact::query()
            ->where('contactable_type', (new PersonalData)->getMorphClass())
            ->where(function ($query) use ($targets): void {
                foreach ($targets as $type => $values) {
                    $query->orWhere(function ($query) use ($type, $values): void {
                        $query->where('type', $type)->whereIn('normalized_value', $values);
                    });
                }
            })
            ->orderBy('id')
            ->get(['id', 'contactable_id']);

        foreach ($contacts as $contact) {
            $registryId = $this->registryIdForCard((int) $contact->contactable_id);

            if ($registryId !== null) {
                return $registryId;
            }
        }

        return null;
    }

    /**
     * The first Registry carrying either fiscal identifier of the row, tried
     * in FISCAL_COLUMNS order.
     *
     * @param  array<string, mixed>  $mapped
     */
    private function matchByFiscalColumns(array $mapped): ?int
    {
        foreach (self::FISCAL_COLUMNS as $column) {
            $registryId = $this->matchByFiscalColumn($column, $mapped);

            if ($registryId !== null) {
                return $registryId;
            }
        }

        return null;
    }

    /**
     * Indexed equality on the allow-listed fiscal column (`$column` only
     * ever comes from FISCAL_COLUMNS, never the mapped field id) narrows the
     * candidate set; MySQL's `utf8mb4_unicode_ci` collation already folds
     * case and trailing whitespace, so this over-matches by at most a
     * handful of rows, never the whole table. The PHP recheck with
     * `ContactValueNormalizer::taxCode` is a cheap safety net over that
     * bounded set, ordered by `id` asc — same "first card wins" semantics
     * as before.
     *
     * @param  string  $column  one of FISCAL_COLUMNS
     * @param  array<string, mixed>  $mapped
     */
    private function matchByFiscalColumn(string $column, array $mapped): ?int
    {
        $target = $this->normalizedFiscalValue($column, $mapped);

        if ($target === null) {
            return null;
        }

        $cards = PersonalData::query()
            ->where('personable_type', (new Registry)->getMorphClass())
            ->where($column, $target)
            ->orderBy('id')
            ->get(['id', $column, 'personable_id']);

        foreach ($cards as $card) {
            if (ContactValueNormalizer::taxCode((string) $card->{$column}) === $target) {
                return (int) $card->personable_id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function buildMatch(int $registryId, array $mapped): LeadDuplicateMatch
    {
        /** @var Registry|null $registry */
        $registry = Registry::query()->with('personalData.contacts')->find($registryId);

        return new LeadDuplicateMatch(
            registryId: $registryId,
            registryName: $registry?->name ?? '',
            matchedOn: $this->matchedOn($registry?->personalData, $mapped),
        );
    }

    /**
     * Every channel of the given card that matches the row's OWN target
     * values, in the canonical MATCH_ORDER — the cumulative "matched_on" set.
     *
     * @param  array<string, mixed>  $mapped
     * @return array<int, string>
     */
    private function matchedOn(?PersonalData $card, array $mapped): array
    {
        if ($card === null) {
            return [];
        }

        $targets = $this->normalizedTargets($mapped);
        $matched = [];

        foreach ($card->contacts as $contact) {
            /** @var ContactTypeEnum $type */
            $type = $contact->type;

            if (in_array($type->value, $matched, true)) {
                continue;
            }

            if (in_array(ContactValueNormalizer::contact($type, (string) $contact->value), $targets[$type->value] ?? [], true)) {
                $matched[] = $type->value;
            }
        }

        foreach (self::FISCAL_COLUMNS as $column) {
            $target = $this->normalizedFiscalValue($column, $mapped);

            if ($target !== null && $card->{$column} !== null && ContactValueNormalizer::taxCode((string) $card->{$column}) === $target) {
                $matched[] = $column;
            }
        }

        return array_values(array_intersect(self::MATCH_ORDER, $matched));
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, array<int, string>> contact type value => normalized candidate values
     */
    private function normalizedTargets(array $mapped): array
    {
        $targets = [];

        foreach (LeadContactFields::map() as $field => $type) {
            $value = trim((string) ($mapped[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $targets[$type->value][] = ContactValueNormalizer::contact($type, $value);
        }

        return $targets;
    }

    /**
     * The row's own value for a fiscal column, normalized, or null when blank.
     * `ContactValueNormalizer::taxCode` (upper + trim) is what the write gate
     * `UniquePersonalDataIdentifier` applies to BOTH columns, so the import and
     * the forms can never disagree on "the same P.IVA".
     *
     * @param  string  $column  one of FISCAL_COLUMNS
     * @param  array<string, mixed>  $mapped
     */
    private function normalizedFiscalValue(string $column, array $mapped): ?string
    {
        $value = trim((string) ($mapped[$column] ?? ''));

        return $value === '' ? null : ContactValueNormalizer::taxCode($value);
    }

    /**
     * The Registry id owning the given personal-data card, or null when the
     * card belongs to a different owner (e.g. a User) — a leads import can
     * only ever match a Registry (spec 0041 D-1).
     */
    private function registryIdForCard(int $cardId): ?int
    {
        /** @var PersonalData|null $card */
        $card = PersonalData::query()->find($cardId, ['id', 'personable_type', 'personable_id']);

        if ($card === null || $card->personable_type !== (new Registry)->getMorphClass()) {
            return null;
        }

        return (int) $card->personable_id;
    }
}

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
 * email/phone/mobile (spec 0033 decision) or, additionally (spec 0036), by
 * `personal_data.tax_code`. Spec 0041 D-1: the contact matched is a Registry,
 * not a Referent. Values are compared NORMALIZED (case/whitespace for email/
 * tax_code, digits-only for phone/mobile) rather than via a raw SQL LIKE/
 * collation trick, mirroring GeoResolver::findByName()/
 * CompaniesImportDefinition::existsInDatabase() — fetch the (bounded)
 * candidate set via Eloquent, compare in PHP, never interpolate the row's
 * value into SQL. Backs `LeadsImportDefinition::resolveDuplicate()`/
 * `resolveDuplicateMatch()`.
 */
final class LeadDuplicateMatcher
{
    /** Canonical, deterministic order for `LeadDuplicateMatch::$matchedOn`. */
    private const array MATCH_ORDER = ['email', 'phone', 'mobile', 'tax_code'];

    /**
     * The row's dominant Registry match — id, display name, and every
     * channel that matches it, cumulative — or null when nothing matches.
     *
     * @param  array<string, mixed>  $mapped  field id => resolved value (after recognizers)
     */
    public function match(array $mapped): ?LeadDuplicateMatch
    {
        // Step 1: an email/phone/mobile Contact match takes priority
        // (unchanged pre-0036 lookup); tax_code is tried only when no
        // contact channel hits, keeping the existing semantics intact.
        $registryId = $this->matchByContact($mapped) ?? $this->matchByTaxCode($mapped);

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
            ->whereIn('type', array_map(
                static fn (ContactTypeEnum $type): string => $type->value,
                array_values(LeadContactFields::map()),
            ))
            ->get(['id', 'type', 'value', 'contactable_id']);

        foreach ($contacts as $contact) {
            /** @var ContactTypeEnum $type */
            $type = $contact->type;
            $normalizedValue = ContactValueNormalizer::contact($type, (string) $contact->value);

            if (! in_array($normalizedValue, $targets[$type->value] ?? [], true)) {
                continue;
            }

            $registryId = $this->registryIdForCard((int) $contact->contactable_id);

            if ($registryId !== null) {
                return $registryId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function matchByTaxCode(array $mapped): ?int
    {
        $target = $this->normalizedTaxCode($mapped);

        if ($target === null) {
            return null;
        }

        $cards = PersonalData::query()
            ->where('personable_type', (new Registry)->getMorphClass())
            ->whereNotNull('tax_code')
            ->get(['id', 'tax_code', 'personable_id']);

        foreach ($cards as $card) {
            if (ContactValueNormalizer::taxCode((string) $card->tax_code) === $target) {
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
        $taxTarget = $this->normalizedTaxCode($mapped);
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

        if ($taxTarget !== null && $card->tax_code !== null && ContactValueNormalizer::taxCode((string) $card->tax_code) === $taxTarget) {
            $matched[] = 'tax_code';
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
     * @param  array<string, mixed>  $mapped
     */
    private function normalizedTaxCode(array $mapped): ?string
    {
        $value = trim((string) ($mapped['tax_code'] ?? ''));

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

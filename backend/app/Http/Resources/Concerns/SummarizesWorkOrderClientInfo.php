<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Enums\ContactTypeEnum;
use App\Enums\SiteTypeEnum;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Tag;
use App\Support\AddressLabel;
use App\Support\ReferentRef;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The "Info cliente" block of a single-offer commessa (spec 0400), the legacy
 * "Gestione commessa" left column: the client's legal seat with VAT number and
 * tax code, tags, email/PEC, phones, Referente 1 and 2, Commerciale, sectors
 * and comments (the anagrafica's general notes).
 *
 * Referente 1 is the anagrafica's primary referent (spec 0398 makes the legacy
 * "Referente 1" the principale); Referente 2 its secondario (spec 0408, the
 * legacy "Referente 2" at import).
 *
 * Address, fiscal ids and contact values are personal data: they travel only
 * when the actor may view that registry / referent (the same boundary as
 * SummarizesWorkOrderContacts); otherwise the keys are there, empty. Relies on
 * WorkOrderService::DETAIL_RELATIONS eager-loading the registry tree; the tag
 * names are the one extra query.
 */
trait SummarizesWorkOrderClientInfo
{
    /**
     * @return array<string, mixed>|null
     */
    private function summarizeClientInfo(Request $request): ?array
    {
        /** @var Registry|null $registry */
        $registry = $this->quote?->opportunity?->registry;

        if ($registry === null) {
            return null;
        }

        // Step 1: personal data of the client, only for an actor who may view it.
        $canViewRegistry = $request->user()?->can('view', $registry) === true;
        $card = $canViewRegistry ? $registry->personalData : null;
        $contacts = $card?->contacts ?? collect();

        // Step 2: the block, the non-personal classification always present.
        return [
            'legal_address' => $card !== null ? $this->clientLegalAddress($card->addresses) : null,
            'vat_number' => $card?->vat_number,
            'tax_code' => $card?->tax_code,
            'tags' => $this->clientTagNames($registry),
            'emails' => $this->clientContacts($contacts, [ContactTypeEnum::Email, ContactTypeEnum::Pec]),
            'phones' => $this->clientContacts($contacts, [ContactTypeEnum::Phone]),
            'referents' => $this->clientReferents($request, $registry),
            'commercial' => ReferentRef::summarize($registry->commercial),
            'sectors' => $registry->sectors->map(static fn (Sector $sector): string => $sector->selectLabel())->values()->all(),
            'comments' => filled($registry->general_notes) ? $registry->general_notes : null,
        ];
    }

    /**
     * The legal seat (legacy "Sede legale"), else the primary address, else the first one.
     *
     * @param  Collection<int, Address>  $addresses
     */
    private function clientLegalAddress(Collection $addresses): ?string
    {
        $address = $addresses->firstWhere('site_type', SiteTypeEnum::LegalSeat)
            ?? $addresses->firstWhere('is_primary', true)
            ?? $addresses->first();
        $label = AddressLabel::singleLine($address);

        return $label === '' ? null : $label;
    }

    /**
     * Contacts of the given types (legacy "Email/PEC", "Telefono"), in the
     * types' order and the primary first, as actionable contact payloads.
     *
     * @param  Collection<int, Contact>  $contacts
     * @param  list<ContactTypeEnum>  $types
     * @return array<int, array{type: string, icon: string|null, label: string, value: string}>
     */
    private function clientContacts(Collection $contacts, array $types): array
    {
        $ordered = $contacts
            ->filter(static fn (Contact $contact): bool => in_array($contact->type, $types, true) && filled($contact->value))
            ->sortBy([
                static fn (Contact $a, Contact $b): int => array_search($a->type, $types, true) <=> array_search($b->type, $types, true),
                static fn (Contact $a, Contact $b): int => $b->is_primary <=> $a->is_primary,
            ])
            ->values();

        return (new PrimaryContactColumn)->format($ordered);
    }

    /**
     * The registry's "Tag" custom field (spec 0398 import `tag_ids`), as names.
     *
     * @return list<string>
     */
    private function clientTagNames(Registry $registry): array
    {
        $tagIds = array_filter((array) ($registry->custom_fields['tags'] ?? []), is_numeric(...));

        if ($tagIds === []) {
            return [];
        }

        return Tag::query()->whereKey($tagIds)->orderBy('name')->pluck('name')->all();
    }

    /**
     * Referente 1 (the principale) and Referente 2 (the secondario, spec 0408), by
     * position: a missing one is null, never replaced by another linked referent.
     * Each carries its primary contacts when the actor may view it.
     *
     * @return list<array<string, mixed>|null>
     */
    private function clientReferents(Request $request, Registry $registry): array
    {
        return array_map(
            fn (?Referent $referent): ?array => $referent === null ? null : ReferentRef::summarize($referent) + [
                'primary_contacts' => $request->user()?->can('view', $referent) === true
                    ? (new PrimaryContactColumn)->formatFor($referent)
                    : [],
            ],
            [$registry->primaryReferent, $registry->secondaryReferent],
        );
    }
}

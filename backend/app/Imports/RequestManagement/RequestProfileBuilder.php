<?php

namespace App\Imports\RequestManagement;

use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Imports\Leads\LeadProfileBuilder;
use App\Models\Registry;

/**
 * The Anagrafica card/contacts/address of an imported row. The shape is the
 * leads import's (LeadProfileBuilder, never re-implemented); this adds the two
 * things a trattativa needs on top: the additional phone numbers of a
 * multi-number cell (stored non-primary, the first stays the principal).
 */
final class RequestProfileBuilder
{
    public function __construct(private readonly LeadProfileBuilder $leadProfiles) {}

    /**
     * @param  array<string, mixed>  $mapped  value set after recognizers
     * @param  ?Registry  $existing  the Registry to merge onto (update_existing), null for a new one
     */
    public function build(array $mapped, ?Registry $existing = null): ProfileData
    {
        $profile = $existing === null
            ? $this->leadProfiles->build($mapped)
            : $this->leadProfiles->buildForUpdate($existing, $mapped);

        return new ProfileData(
            card: $profile->card,
            contacts: $this->withExtraPhones($profile->contacts, (array) ($mapped['extra_phones'] ?? [])),
            addresses: $profile->addresses,
        );
    }

    /**
     * @param  array<int, ContactInput>|null  $contacts
     * @param  array<int, string>  $extraPhones
     * @return array<int, ContactInput>|null
     */
    private function withExtraPhones(?array $contacts, array $extraPhones): ?array
    {
        if ($extraPhones === []) {
            return $contacts;
        }

        $contacts ??= [];
        $known = array_map(static fn (ContactInput $input): string => $input->data->value, $contacts);

        foreach ($extraPhones as $phone) {
            if (! in_array($phone, $known, true)) {
                $contacts[] = new ContactInput(null, new CreateContact(ContactTypeEnum::Phone, $phone, null, false));
            }
        }

        return $contacts;
    }
}

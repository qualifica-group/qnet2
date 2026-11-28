<?php

namespace Database\Seeders\Support;

use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use App\Services\ContactService;
use App\Services\ReferentService;
use App\Support\ContactValueNormalizer;

/**
 * What a request sheet row adds to a client's anagrafica (user directives
 * 2026-11-07/28): the company's contact person among its referents, and the new
 * phones/emails of a client already on file. Through the referents' and contacts'
 * own services; nothing else of the anagrafica changes.
 */
final class RequestSheetContacts
{
    public function __construct(
        private readonly ReferentService $referents,
        private readonly ContactService $contacts,
    ) {}

    /**
     * A company's contact person ("Referente Azienda"), created as a new referent
     * and listed among the company's referents (user decision 2026-11-07). Internal
     * and with no type, like the referents already linked to client anagrafiche.
     *
     * @param  array{first: ?string, last: ?string, title?: string, phones?: list<string>}  $person
     */
    public function addContactPerson(User $actor, int $registryId, array $person): void
    {
        $registry = Registry::query()->findOrFail($registryId);
        $name = trim(($person['first'] ?? '').' '.($person['last'] ?? ''));

        // A company already listing a referent of that name keeps it, never a twin.
        if ($registry->referents()->where('referents.name', $name)->exists()) {
            return;
        }

        $contacts = array_map(
            static fn (string $phone, int $index): ContactInput => new ContactInput(null, new CreateContact(ContactTypeEnum::Phone, $phone, null, $index === 0)),
            $person['phones'] ?? [],
            array_keys($person['phones'] ?? []),
        );

        $referent = $this->referents->create(
            $actor,
            new CreateReferentData(null, ReferentContactScopeEnum::Internal->value, $person['title'] ?? null),
            new ProfileData(new CreatePersonalData(PersonalDataTypeEnum::Individual, $person['first'] ?? null, $person['last'] ?? null), $contacts === [] ? null : $contacts),
        );

        $registry->referents()->syncWithoutDetaching([$referent->id]);
    }

    /**
     * Adds to a client on file the row's phones and emails it does not hold yet
     * (user decision 2026-11-28: same company, other contacts -> on its
     * anagrafica), as secondary contacts; nothing else of the anagrafica changes.
     *
     * @param  array<string, mixed>  $row
     */
    public function addMissingContacts(int $registryId, array $row): void
    {
        $card = PersonalData::query()->where('personable_type', (new Registry)->getMorphClass())
            ->where('personable_id', $registryId)->with('contacts')->firstOrFail();
        $held = $card->contacts->map(static fn ($contact): string => $contact->type->value.':'.$contact->normalized_value)->all();
        $wanted = [
            ...array_map(static fn (string $phone): array => [ContactTypeEnum::Phone, $phone], $row['phones']),
            ...array_map(static fn (string $email): array => [ContactTypeEnum::Email, $email], RequestSheetLookups::emailsOf($row)),
        ];

        foreach ($wanted as [$type, $value]) {
            $key = $type->value.':'.ContactValueNormalizer::contact($type, $value);

            if (! in_array($key, $held, true)) {
                $this->contacts->createFor($card, new CreateContact($type, $value, null, false));
                $held[] = $key;
            }
        }
    }
}

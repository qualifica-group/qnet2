<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Enums\ContactTypeEnum;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Services\AddressService;
use App\Services\ContactService;
use App\Services\PersonalDataService;
use Illuminate\Validation\ValidationException;

/**
 * Writes the client anagraphic block the work panel edits (spec 0049
 * amendment) onto the Registry's PersonalData card, reusing the owner-agnostic
 * ContactService/AddressService verbatim — the same path the Registries module
 * writes through, so the two never drift.
 *
 * Three deliberately DIFFERENT write semantics:
 *
 *  - identity: a create-or-update of the card itself (PersonalDataService::
 *    upsertFor), plus the derivation of the denormalized `registries.name`
 *    from it — exactly what RegistryProfileWriter does, so a client renamed
 *    from this panel is renamed everywhere. Written FIRST, so a client whose
 *    card does not exist yet gets one before contacts/address need it.
 *  - contacts: authoritative sync (ContactService::sync). The panel loads the
 *    card's FULL contact set into its buffer, so what it submits is the whole
 *    truth and a removed row must really be deleted.
 *  - address: a single create-or-update row, never a sync. The panel only ever
 *    edits the card's PRIMARY address; a client may legitimately own others
 *    (registries allow many) and those must survive a request-panel save.
 *
 * The caller owns the surrounding transaction (RequestManagementService::
 * updateWork already opens one), so this writer never opens an outer one.
 */
final class RequestClientProfileWriter
{
    /** The inline payload key addressing the client's primary telephone channel (spec 0055, D-7). */
    private const string CLIENT_PHONE_KEY = 'client_phone';

    /**
     * Inline payload key -> PersonalData attribute, for the identity fields
     * a single cell can edit (spec 0055, D-7/D-8).
     *
     * @var array<string, string>
     */
    private const array IDENTITY_ATTRIBUTES = [
        'client_first_name' => 'first_name',
        'client_last_name' => 'last_name',
        'client_tax_code' => 'tax_code',
        'client_vat_number' => 'vat_number',
    ];

    /**
     * The SPARSE single-field keys (spec 0055, D-7) — the inline grid edits
     * one cell at a time and can never submit a whole block.
     *
     * @var array<int, string>
     */
    private const array CLIENT_FIELD_KEYS = [
        'client_first_name',
        'client_last_name',
        'client_tax_code',
        'client_vat_number',
        self::CLIENT_PHONE_KEY,
    ];

    /**
     * The whole-block keys the work panel submits (identity card, the
     * authoritative contact set, the primary address).
     *
     * @var array<int, string>
     */
    private const array BLOCK_KEYS = ['client_identity', 'client_contacts', 'client_address'];

    /**
     * The contact kinds the "Telefono" column may address — the same pair
     * RequestRowMapper::primaryPhone() projects, so the cell writes back
     * exactly the row it displays.
     *
     * @var array<int, ContactTypeEnum>
     */
    private const array TELEPHONE_TYPES = [ContactTypeEnum::Phone, ContactTypeEnum::Mobile];

    public function __construct(
        private readonly PersonalDataService $personalData,
        private readonly ContactService $contacts,
        private readonly AddressService $addresses,
    ) {}

    /**
     * Applies every client anagraphic key of a work-panel/inline PATCH: the
     * SPARSE single-field keys the grid's inline editor submits (spec
     * 0055, D-7) and the whole-block keys the work panel submits. Both
     * channels land here, so "which keys are client keys" is known in exactly
     * one place — this writer, which already knows where each field
     * physically lives.
     *
     * The single-field writes report into $changed/$old like every other
     * operational field, so RequestManagementService's activity entry covers
     * them too (D-9): an anagraphic edit never touches `opportunities`, so
     * the model's own LogsModelActivity would otherwise leave no trace at all.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException when the client has no anagraphic card to write against
     */
    public function applyTo(Opportunity $opportunity, array $data, array &$changed, array &$old): void
    {
        foreach (self::CLIENT_FIELD_KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key] === null ? null : (string) $data[$key];
            $previous = $this->writeClientField($opportunity, $key, $value);

            $opportunity->unsetRelation('registry');

            if ($previous !== $value) {
                $old[$key] = $previous;
                $changed[$key] = $value;
            }
        }

        if (array_intersect_key($data, array_flip(self::BLOCK_KEYS)) === []) {
            return;
        }

        $this->write(
            $opportunity,
            $data['client_identity'] ?? null,
            $data['client_contacts'] ?? null,
            $data['client_address'] ?? null,
        );

        $opportunity->unsetRelation('registry');
    }

    /**
     * @param  array<int, ContactInput>|null  $contacts
     *
     * @throws ValidationException when the client has no anagraphic card to write against
     */
    public function write(Opportunity $opportunity, ?CreatePersonalData $identity, ?array $contacts, ?AddressInput $address): void
    {
        if ($identity !== null) {
            $this->writeIdentity($opportunity, $identity);
        }

        if ($contacts === null && $address === null) {
            return;
        }

        $card = $this->resolveCard($opportunity, $contacts !== null ? 'client_contacts' : 'client_address');

        if ($contacts !== null) {
            $this->contacts->sync($card, $contacts);
        }

        if ($address !== null) {
            $this->writeAddress($card, $address);
        }
    }

    /**
     * Upserts the client's card identity and re-derives `registries.name` from
     * it in the same write, the way RegistryProfileWriter does — the name is
     * denormalized display data, and letting the two diverge would surface a
     * stale client name in every list that reads it.
     *
     * The card relation is dropped afterwards so the contacts/address writes
     * resolve the card just persisted (relevant when this call created it).
     *
     * @throws ValidationException when the request has no client to write to
     */
    private function writeIdentity(Opportunity $opportunity, CreatePersonalData $identity): void
    {
        $registry = $opportunity->registry;

        if (! $registry instanceof Registry) {
            throw ValidationException::withMessages([
                'client_identity' => 'The request has no client to write to.',
            ]);
        }

        $registry->forceFill(['name' => $identity->displayName()])->save();
        $this->personalData->upsertFor($registry, $identity);
        $registry->unsetRelation('personalData');
    }

    /**
     * Writes ONE client anagraphic field (spec 0055, D-7): the inline-edit
     * channel edits a single cell, so it can never submit the whole
     * `client_identity` block nor the whole contact set the way the work
     * panel does. Two deliberately different semantics behind one entry
     * point, keyed by the payload key the column maps to:
     *
     *  - identity (`client_first_name`/`client_last_name`/`client_tax_code`/
     *    `client_vat_number`):
     *    the DTO is rebuilt from the card's CURRENT values with the single
     *    edited field replaced, then written through the same writeIdentity()
     *    the panel uses — so `registries.name` is re-derived identically and
     *    no untouched field of the card is nulled.
     *  - phone (`client_phone`): NEVER ContactService::sync(), which is
     *    authoritative over the whole set and would delete every other
     *    contact of a client the inline editor never loaded. The existing
     *    primary phone/mobile row is updated in place (its `type` preserved:
     *    a mobile stays a mobile); with none present, one is created as a
     *    primary `phone`.
     *
     * A client with no anagraphic card is a 422, never a silent create: this
     * channel has no way to know whether the card should be an individual or
     * a company.
     *
     * Returns the value held BEFORE the write, so the caller can log the
     * operational change (spec 0055, D-9) without resolving the card a second
     * time — this writer is already the only place that knows where each of
     * those fields physically lives.
     *
     * @throws ValidationException the key is unknown, or the client has no card to write to
     */
    public function writeClientField(Opportunity $opportunity, string $key, ?string $value): ?string
    {
        $card = $this->resolveCard($opportunity, $key);

        if ($key === self::CLIENT_PHONE_KEY) {
            return $this->writePrimaryPhone($card, $value);
        }

        $attribute = self::IDENTITY_ATTRIBUTES[$key] ?? null;

        if ($attribute === null) {
            throw ValidationException::withMessages([
                $key => 'This client field cannot be written.',
            ]);
        }

        $previous = $card->{$attribute};

        $this->writeIdentity($opportunity, $this->identityWith($card, $attribute, $value));

        return $previous;
    }

    /**
     * The card's current identity as a DTO, with $attribute replaced by
     * $value. Every other property is carried over verbatim so a sparse
     * single-field write can never blank one the editor never showed.
     */
    private function identityWith(PersonalData $card, string $attribute, ?string $value): CreatePersonalData
    {
        $current = [
            'first_name' => $card->first_name,
            'last_name' => $card->last_name,
            'tax_code' => $card->tax_code,
            'vat_number' => $card->vat_number,
        ];

        $current[$attribute] = $value;

        return new CreatePersonalData(
            type: $card->type,
            firstName: $current['first_name'],
            lastName: $current['last_name'],
            companyName: $card->company_name,
            taxCode: $current['tax_code'],
            vatNumber: $current['vat_number'],
            sdiCode: $card->sdi_code,
            birthDate: $card->birth_date?->format('Y-m-d'),
            birthCityId: $card->birth_city_id,
            residenceCityId: $card->residence_city_id,
            gender: $card->gender?->value,
        );
    }

    /**
     * Update-in-place of the card's primary telephone channel (see
     * writeClientField's docblock for why this is not a sync). Returns the
     * number held before the write.
     */
    private function writePrimaryPhone(PersonalData $card, ?string $value): ?string
    {
        // Queried, not read off the loaded relation: this writer must behave
        // the same whether its caller eager-loaded the contacts or not
        // (Model::preventLazyLoading is active outside production).
        $existing = $card->contacts()
            ->where('is_primary', true)
            ->whereIn('type', array_map(static fn (ContactTypeEnum $type): string => $type->value, self::TELEPHONE_TYPES))
            ->first();

        $blank = $value === null || trim($value) === '';
        $previous = $existing instanceof Contact ? $existing->value : null;

        if ($existing instanceof Contact) {
            // Clearing the cell removes the channel: an empty `contacts.value`
            // would be a phantom row that every consumer would have to filter.
            if ($blank) {
                $this->contacts->delete($existing);
            } else {
                $this->contacts->update($existing, new CreateContact(
                    type: $existing->type,
                    value: $value,
                    label: $existing->label,
                    isPrimary: true,
                ));
            }
        } elseif (! $blank) {
            $this->contacts->createFor($card, new CreateContact(
                type: ContactTypeEnum::Phone,
                value: $value,
                isPrimary: true,
            ));
        }

        $card->unsetRelation('contacts');

        return $previous;
    }

    /**
     * The Registry's card, or a 422 keyed on the submitted block: without a
     * card there is no owner to attach contacts/addresses to (Registry itself
     * is not a valid contactable/addressable type).
     *
     * @throws ValidationException
     */
    private function resolveCard(Opportunity $opportunity, string $errorKey): PersonalData
    {
        $card = $opportunity->registry?->personalData;

        if ($card === null) {
            throw ValidationException::withMessages([
                $errorKey => 'The client has no anagraphic card to write to.',
            ]);
        }

        return $card;
    }

    /**
     * Create-or-update of the single edited address. An id that does not
     * belong to this card is treated as a create, never an update, mirroring
     * ContactService/AddressService::sync's own spoofed-id rule.
     */
    private function writeAddress(PersonalData $card, AddressInput $address): void
    {
        $existing = $address->id === null
            ? null
            : $card->addresses()->whereKey($address->id)->first();

        if ($existing === null) {
            $this->addresses->createFor($card, $address->data);

            return;
        }

        $this->addresses->update($existing, $this->preserveUnedited($existing, $address->data));
    }

    /**
     * AddressService::update replaces the row's attributes wholesale, but this
     * panel only edits the postal fields. Carry the dimensions it never shows
     * (primary flag, site type, coordinates) over from the persisted row, so a
     * save here can neither demote the owner's primary address (ADR 0010) nor
     * silently reset a registry's site type to the `billing` default.
     */
    private function preserveUnedited(Address $existing, CreateAddress $edited): CreateAddress
    {
        return new CreateAddress(
            line1: $edited->line1,
            line2: $edited->line2,
            postalCode: $edited->postalCode,
            cityId: $edited->cityId,
            provinceId: $edited->provinceId,
            stateId: $edited->stateId,
            countryId: $edited->countryId,
            latitude: $existing->latitude === null ? null : (string) $existing->latitude,
            longitude: $existing->longitude === null ? null : (string) $existing->longitude,
            isPrimary: (bool) $existing->is_primary,
            siteType: $existing->site_type,
        );
    }
}

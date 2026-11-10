<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Aligns the card, the primary contacts and the primary address of a registry or a
 * referent (spec 0375 D-11). A value the legacy leaves empty never empties a contact
 * or an address here, and the secondary ones are not touched (AC-018). The services
 * are not used: their authoritative sync would delete what the legacy does not send.
 */
final class RegistryProfileAligner
{
    /**
     * @return list<array{0: class-string<Model>, 1: mixed}>
     */
    public function touchSubjects(Model $owner): array
    {
        $card = $this->card($owner);

        if ($card === null) {
            return [];
        }

        return [
            [PersonalData::class, $card->getKey()],
            [Contact::class, Contact::query()->where('contactable_type', $card->getMorphClass())->where('contactable_id', $card->getKey())->select('id')],
            [Address::class, Address::query()->where('addressable_type', $card->getMorphClass())->where('addressable_id', $card->getKey())->select('id')],
        ];
    }

    /**
     * @param  list<string>  $cardColumns
     * @return array<string, string|int|float|bool|null>
     */
    public function snapshot(Model $owner, array $cardColumns): array
    {
        $card = $this->card($owner);

        if ($card === null) {
            return [];
        }

        $values = [];

        foreach ($cardColumns as $column) {
            $value = $card->getRawOriginal($column);
            $values[$column] = $column === 'birth_date' && is_string($value) ? substr($value, 0, 10) : $value;
        }

        foreach ($card->contacts()->where('is_primary', true)->orderBy('id')->get() as $contact) {
            $values['contact.'.$contact->getRawOriginal('type')] = $contact->getRawOriginal('value');
        }

        return [...$values, ...$this->addressSnapshot($card)];
    }

    /**
     * @param  list<string>  $cardColumns  the columns the legacy decides for this owner
     * @return bool whether something was written
     */
    public function align(Model $owner, ProfileData $profile, array $cardColumns, bool $streetSent): bool
    {
        // Step 1: the denormalized name, saved by the caller with the owner.
        $owner->forceFill(['name' => $profile->card->displayName()]);

        // Step 2: the card, then the channels and the address it owns.
        $card = $this->card($owner) ?? $owner->personalData()->create(['type' => $profile->card->type->value]);
        $changed = $this->alignCard($card, $profile, $cardColumns);
        $changed = $this->alignContacts($card, $profile) || $changed;

        return $this->alignAddress($card, $profile, $streetSent) || $changed;
    }

    private function card(Model $owner): ?PersonalData
    {
        return $owner->personalData()->first();
    }

    /**
     * @param  list<string>  $cardColumns
     */
    private function alignCard(PersonalData $card, ProfileData $profile, array $cardColumns): bool
    {
        $card->forceFill(Arr::only($profile->card->toAttributes(), $cardColumns));

        if (! $card->isDirty()) {
            return false;
        }

        $card->saveQuietly();

        return true;
    }

    private function alignContacts(PersonalData $card, ProfileData $profile): bool
    {
        $changed = false;

        foreach ($this->primaryContacts($profile) as $input) {
            $changed = $this->alignContact($card, $input) || $changed;
        }

        return $changed;
    }

    /**
     * One input per type, the last one winning, as the one-primary-per-type invariant does at import.
     *
     * @return array<string, ContactInput>
     */
    private function primaryContacts(ProfileData $profile): array
    {
        $inputs = [];

        foreach ($profile->contacts ?? [] as $input) {
            if ($input->data->isPrimary) {
                $inputs[$input->data->type->value] = $input;
            }
        }

        return $inputs;
    }

    /**
     * `save()` and not a quiet save: `normalized_value` is kept by the model's saving event.
     */
    private function alignContact(PersonalData $card, ContactInput $input): bool
    {
        $current = $card->primaryContact($input->data->type);

        if ($current === null) {
            $card->contacts()->create($input->data->toAttributes());

            return true;
        }

        if ($current->value === $input->data->value) {
            return false;
        }

        $current->forceFill(['value' => $input->data->value])->save();

        return true;
    }

    private function alignAddress(PersonalData $card, ProfileData $profile, bool $streetSent): bool
    {
        $data = collect($profile->addresses ?? [])->first(fn ($input): bool => $input->data->isPrimary)?->data;

        if ($data === null) {
            return false;
        }

        $address = $card->addresses()->where('is_primary', true)->first();

        if ($address === null) {
            $card->addresses()->create($data->toAttributes());

            return true;
        }

        $address->forceFill($this->addressChanges($data, $streetSent));

        if (! $address->isDirty()) {
            return false;
        }

        $address->saveQuietly();

        return true;
    }

    /**
     * Only what the legacy really decides: a geo level that does not resolve keeps its value.
     *
     * @return array<string, string|int>
     */
    private function addressChanges(CreateAddress $data, bool $streetSent): array
    {
        return array_filter([
            'line1' => $streetSent ? $data->line1 : null,
            'postal_code' => $data->postalCode,
            'city_id' => $data->cityId,
            'province_id' => $data->provinceId,
            'state_id' => $data->stateId,
            'country_id' => $data->countryId,
        ], static fn (string|int|null $value): bool => $value !== null);
    }

    /**
     * @return array<string, string|null>
     */
    private function addressSnapshot(PersonalData $card): array
    {
        $address = $card->addresses()->where('is_primary', true)->with(['city', 'province', 'state', 'country'])->first();

        if ($address === null) {
            return [];
        }

        return [
            'address.line1' => $address->getRawOriginal('line1'),
            'address.postal_code' => $address->getRawOriginal('postal_code'),
            'address.city' => $address->city?->name,
            'address.province' => $address->province?->name,
            'address.state' => $address->state?->name,
            'address.country' => $address->country?->name,
        ];
    }
}

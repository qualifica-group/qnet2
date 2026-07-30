<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Authorization\AuthorizationRegistry;
use App\Enums\ContactTypeEnum;
use App\Models\Address;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\User;
use App\Support\AddressLabel;

/**
 * Resolves the `client`, `referent`, `commercial`, `reporter` and
 * `supervisor` variable categories (spec 0069's frozen catalogue) — every
 * category whose source is a personal-data card, split out of
 * VariableResolver to keep it under the file-size soft limit
 * (engineering.md §6).
 *
 * D-6 (non-negotiable, spec 0069): `client.vat_number`/`tax_code`/`sdi_code`
 * are the ONLY tokens in the whole catalogue sourced from a `personal_data`
 * column considered PII by this module (see
 * DocumentLayoutVariableCatalog's class docblock) — masked to an empty
 * string when the actor's `registries` field permissions hide the matching
 * `personal_data.{field}` key. Reuses the exact same
 * AuthorizationRegistry::resolve()->fieldPermissions() merge the catalogue
 * itself consults, rather than a second implementation of the same rule.
 */
final class PartyFieldResolver
{
    private const string CLIENT_RESOURCE = 'registries';

    /** @var array<int, string> */
    private const array CLIENT_MASKABLE_KEYS = ['vat_number', 'tax_code', 'sdi_code'];

    public function __construct(private readonly AuthorizationRegistry $authorizationRegistry) {}

    public function client(string $key, Quote $quote, User $actor): ?string
    {
        $registry = $quote->opportunity?->registry;

        if (in_array($key, self::CLIENT_MASKABLE_KEYS, true) && ! $this->personalDataFieldVisible($actor, $key)) {
            return '';
        }

        if ($registry === null) {
            return $this->isKnownClientKey($key) ? '' : null;
        }

        $personalData = $registry->personalData;
        $address = $this->primaryAddress($personalData);

        return match ($key) {
            'name' => ValueFormatter::text($registry->name),
            'full_name' => ValueFormatter::text($personalData?->full_name),
            'type' => ValueFormatter::text($personalData?->type?->value),
            'vat_number' => ValueFormatter::text($personalData?->vat_number),
            'tax_code' => ValueFormatter::text($personalData?->tax_code),
            'sdi_code' => ValueFormatter::text($personalData?->sdi_code),
            'email' => ValueFormatter::text($this->contactValue($personalData, ContactTypeEnum::Email)),
            'phone' => ValueFormatter::text($this->contactValue($personalData, ContactTypeEnum::Phone)),
            'address' => AddressLabel::singleLine($address),
            'address_line1' => ValueFormatter::text($address?->line1),
            'address_postal_code' => ValueFormatter::text($address?->postal_code),
            'address_city' => ValueFormatter::text($address?->city?->localizedName()),
            'address_province' => ValueFormatter::text($address?->province?->localizedName()),
            'address_state' => ValueFormatter::text($address?->state?->localizedName()),
            'address_country' => ValueFormatter::text($address?->country?->localizedName()),
            default => null,
        };
    }

    public function referent(string $key, Quote $quote): ?string
    {
        $referent = $quote->opportunity?->referent;

        return match ($key) {
            'name' => ValueFormatter::text($referent?->name),
            'type_name' => ValueFormatter::text($referent?->referentType?->name),
            'email' => ValueFormatter::text($this->contactValue($referent?->personalData, ContactTypeEnum::Email)),
            'phone' => ValueFormatter::text($this->contactValue($referent?->personalData, ContactTypeEnum::Phone)),
            default => null,
        };
    }

    public function commercial(string $key, Quote $quote): ?string
    {
        return $this->referentRoleField($key, $quote->commercial);
    }

    public function reporter(string $key, Quote $quote): ?string
    {
        return $this->referentRoleField($key, $quote->reporter);
    }

    public function supervisor(string $key, Quote $quote): ?string
    {
        $supervisor = $quote->supervisor;

        return match ($key) {
            'name' => ValueFormatter::text($supervisor?->name),
            'email' => ValueFormatter::text($supervisor?->email),
            default => null,
        };
    }

    private function referentRoleField(string $key, ?Referent $referent): ?string
    {
        return match ($key) {
            'name' => ValueFormatter::text($referent?->name),
            'email' => ValueFormatter::text($this->contactValue($referent?->personalData, ContactTypeEnum::Email)),
            'phone' => ValueFormatter::text($this->contactValue($referent?->personalData, ContactTypeEnum::Phone)),
            default => null,
        };
    }

    private function contactValue(?PersonalData $personalData, ContactTypeEnum $type): ?string
    {
        return $personalData?->primaryContact($type)?->value;
    }

    private function primaryAddress(?PersonalData $personalData): ?Address
    {
        if ($personalData === null) {
            return null;
        }

        return PrimaryAddressPicker::pick($personalData->addresses);
    }

    /**
     * D-6: whether $actor's `registries` field permissions show
     * `personal_data.{$key}` — the exact same check
     * DocumentLayoutVariableCatalog::visiblePersonalDataFields() performs for
     * the catalogue, applied here to the RESOLUTION path instead.
     */
    private function personalDataFieldVisible(User $actor, string $key): bool
    {
        $permissions = $this->authorizationRegistry->resolve(self::CLIENT_RESOURCE)->fieldPermissions($actor, null);

        return ($permissions["personal_data.{$key}"] ?? null)?->visible ?? true;
    }

    private function isKnownClientKey(string $key): bool
    {
        return in_array($key, [
            'name', 'full_name', 'type', 'vat_number', 'tax_code', 'sdi_code', 'email', 'phone',
            'address', 'address_line1', 'address_postal_code', 'address_city', 'address_province',
            'address_state', 'address_country',
        ], true);
    }
}

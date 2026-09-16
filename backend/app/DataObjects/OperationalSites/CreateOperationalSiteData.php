<?php

namespace App\DataObjects\OperationalSites;

use App\DataObjects\PersonalData\CreateAddress;

/**
 * Validated payload for creating an operational site (POST
 * /api/operational-sites, spec 0011).
 *
 * Declared DTO (no "magic flying array") so the StoreOperationalSiteRequest →
 * OperationalSiteService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * Unlike CreateCompanyData's nested `address` object, the payload here is
 * FLAT (line1/postal_code + the geo FK cascade): the site's single address IS
 * the resource, there is no separate "site name" field. `toAddress()` builds
 * the CreateAddress AddressService::createFor() expects.
 */
final readonly class CreateOperationalSiteData
{
    public function __construct(
        public string $line1,
        public ?string $postalCode = null,
        public ?int $countryId = null,
        public ?int $stateId = null,
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?string $alias = null,
        public bool $isActive = true,
    ) {}

    /**
     * Build from the validated StoreOperationalSiteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            line1: (string) $data['line1'],
            postalCode: $data['postal_code'] ?? null,
            countryId: isset($data['country_id']) ? (int) $data['country_id'] : null,
            stateId: isset($data['state_id']) ? (int) $data['state_id'] : null,
            provinceId: isset($data['province_id']) ? (int) $data['province_id'] : null,
            cityId: isset($data['city_id']) ? (int) $data['city_id'] : null,
            alias: $data['alias'] ?? null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    public function toAddress(): CreateAddress
    {
        return new CreateAddress(
            line1: $this->line1,
            postalCode: $this->postalCode,
            cityId: $this->cityId,
            provinceId: $this->provinceId,
            stateId: $this->stateId,
            countryId: $this->countryId,
            isPrimary: true,
        );
    }
}
